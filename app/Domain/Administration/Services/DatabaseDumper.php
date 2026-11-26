<?php

namespace App\Domain\Administration\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/** Pure-PHP SQL dump/restore for MySQL/MariaDB and SQLite (no mysqldump binary needed on shared hosting). */
class DatabaseDumper
{
    public function connection(): Connection
    {
        return DB::connection();
    }

    public function driver(): string
    {
        return $this->connection()->getDriverName();
    }

    /** Writes a SQL dump of the default connection to $path. */
    public function dump(string $path): void
    {
        $connection = $this->connection();
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write '.$path);
        }

        try {
            fwrite($handle, '-- StageTrack Enterprise database dump ('.$driver.') '.now()->toIso8601String()."\n");
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->dumpMysql($pdo, $handle);
            } elseif ($driver === 'sqlite') {
                $this->dumpSqlite($pdo, $handle);
            } else {
                throw new RuntimeException("Database driver [{$driver}] is not supported for backups.");
            }
        } finally {
            fclose($handle);
        }
    }

    /** Executes every statement of a dump file against the default connection. */
    public function restore(string $path): int
    {
        $connection = $this->connection();
        $driver = $connection->getDriverName();
        $sql = (string) file_get_contents($path);
        $count = 0;
        $this->backslashEscapes = in_array($driver, ['mysql', 'mariadb'], true);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            $connection->unprepared('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($this->splitStatements($sql) as $statement) {
                $connection->unprepared($statement);
                $count++;
            }
        } finally {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                $connection->unprepared('PRAGMA foreign_keys = ON');
            }
        }

        return $count;
    }

    /** @return list<string> */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $this->backslashEscapes && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-' && trim($buffer) === '') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;

                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            }
            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';

                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    private bool $backslashEscapes = true;

    /** @param resource $handle */
    private function dumpMysql(PDO $pdo, $handle): void
    {
        $this->backslashEscapes = true;
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as [$table]) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
            fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n{$create};\n");
            $this->dumpRows($pdo, $handle, $quoted, fn ($v) => $this->mysqlValue($pdo, $v));
            fwrite($handle, "\n");
        }
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** @param resource $handle */
    private function dumpSqlite(PDO $pdo, $handle): void
    {
        $this->backslashEscapes = false;
        $objects = $pdo->query("SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($objects as $object) {
            if ($object['type'] !== 'table') {
                continue;
            }
            $quoted = '"'.str_replace('"', '""', $object['name']).'"';
            fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n{$object['sql']};\n");
            $this->dumpRows($pdo, $handle, $quoted, fn ($v) => $this->sqliteValue($pdo, $v));
            fwrite($handle, "\n");
        }
        foreach ($objects as $object) {
            if ($object['type'] === 'index' || $object['type'] === 'trigger' || $object['type'] === 'view') {
                fwrite($handle, $object['sql'].";\n");
            }
        }
    }

    /** @param resource $handle */
    private function dumpRows(PDO $pdo, $handle, string $quotedTable, callable $encode): void
    {
        $statement = $pdo->query('SELECT * FROM '.$quotedTable);
        $batch = [];
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            $batch[] = '('.implode(',', array_map($encode, $row)).')';
            if (count($batch) >= 100) {
                fwrite($handle, 'INSERT INTO '.$quotedTable.' VALUES '.implode(",\n", $batch).";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($handle, 'INSERT INTO '.$quotedTable.' VALUES '.implode(",\n", $batch).";\n");
        }
    }

    private function mysqlValue(PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value), is_float($value) => (string) $value,
            ! mb_check_encoding((string) $value, 'UTF-8') => '0x'.bin2hex((string) $value),
            default => $pdo->quote((string) $value),
        };
    }

    private function sqliteValue(PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value), is_float($value) => (string) $value,
            ! mb_check_encoding((string) $value, 'UTF-8') => "X'".bin2hex((string) $value)."'",
            default => "'".str_replace("'", "''", (string) $value)."'",
        };
    }
}
