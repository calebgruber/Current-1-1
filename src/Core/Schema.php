<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A very small schema builder so the same migration works on SQLite and MySQL.
 */
final class Schema
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(string $table, callable $define): void
    {
        $blueprint = new Blueprint();
        $define($blueprint);
        $mysql = $this->db->driver === 'mysql';

        $lines = [];
        foreach ($blueprint->columns as $column) {
            $lines[] = $this->db->ident($column['name']) . ' ' . $this->columnSql($column, $mysql);
        }
        foreach ($blueprint->foreign as [$column, $references, $onDelete]) {
            $lines[] = sprintf(
                'FOREIGN KEY (%s) REFERENCES %s(id) ON DELETE %s',
                $this->db->ident($column),
                $this->db->ident($references),
                $onDelete,
            );
        }
        foreach ($blueprint->unique as $columns) {
            $lines[] = 'UNIQUE (' . implode(', ', array_map($this->db->ident(...), $columns)) . ')';
        }

        $sql = 'CREATE TABLE ' . $this->db->ident($table) . " (\n  " . implode(",\n  ", $lines) . "\n)";
        if ($mysql) {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $this->db->run($sql);

        foreach ($blueprint->indexes as $columns) {
            $name = $table . '_' . implode('_', $columns) . '_index';
            $this->db->run(sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->db->ident($name),
                $this->db->ident($table),
                implode(', ', array_map($this->db->ident(...), $columns)),
            ));
        }
    }

    public function drop(string $table): void
    {
        $this->db->run('DROP TABLE IF EXISTS ' . $this->db->ident($table));
    }

    private function columnSql(array $c, bool $mysql): string
    {
        if ($c['type'] === 'id') {
            return $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        $type = match ($c['type']) {
            'string' => 'VARCHAR(' . $c['length'] . ')',
            'text' => $mysql ? 'MEDIUMTEXT' : 'TEXT',
            'integer' => $mysql ? 'INT' : 'INTEGER',
            'foreign' => $mysql ? 'BIGINT UNSIGNED' : 'INTEGER',
            'boolean' => $mysql ? 'TINYINT(1)' : 'INTEGER',
            'decimal' => 'DECIMAL(10,2)',
            'date' => 'DATE',
            'datetime' => $mysql ? 'DATETIME' : 'TEXT',
        };
        $sql = $type . ($c['nullable'] ? ' NULL' : ' NOT NULL');
        if (array_key_exists('default', $c)) {
            $default = $c['default'];
            $sql .= ' DEFAULT ' . match (true) {
                $default === null => 'NULL',
                is_bool($default) => $default ? '1' : '0',
                is_int($default) || is_float($default) => (string) $default,
                default => "'" . str_replace("'", "''", (string) $default) . "'",
            };
        }

        return $sql;
    }
}
