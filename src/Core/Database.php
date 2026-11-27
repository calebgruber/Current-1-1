<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

final class Database
{
    public readonly PDO $pdo;
    public readonly string $driver;

    public function __construct(array $config)
    {
        $this->driver = ($config['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($this->driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'] ?? 'localhost',
                (int) ($config['port'] ?? 3306),
                $config['database'] ?? '',
            );
            $this->pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', $options);
            $this->pdo->exec("SET time_zone = '+00:00'");
        } else {
            $path = $config['path'] ?? BASE_PATH . '/storage/database.sqlite';
            $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach (array_values(array_is_list($params) ? $params : []) as $i => $value) {
            $stmt->bindValue($i + 1, $value, $this->type($value));
        }
        if (! array_is_list($params)) {
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . ltrim((string) $key, ':'), $value, $this->type($value));
            }
        }
        $stmt->execute();

        return $stmt;
    }

    private function type(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function find(string $table, int $id): ?array
    {
        return $this->one('SELECT * FROM ' . $this->ident($table) . ' WHERE id = ?', [$id]);
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->ident($table),
            implode(', ', array_map($this->ident(...), $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );
        $this->run($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $sets = implode(', ', array_map(fn ($c) => $this->ident($c) . ' = ?', array_keys($data)));
        $this->run('UPDATE ' . $this->ident($table) . ' SET ' . $sets . ' WHERE id = ?', [...array_values($data), $id]);
    }

    public function delete(string $table, int $id): void
    {
        $this->run('DELETE FROM ' . $this->ident($table) . ' WHERE id = ?', [$id]);
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        if ($this->driver === 'mysql') {
            return (bool) $this->value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table],
            );
        }

        return (bool) $this->value("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
    }

    /** Quote an identifier. Only letters, numbers and underscores are allowed. */
    public function ident(string $name): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $name);
        }

        return $this->driver === 'mysql' ? '`' . $name . '`' : '"' . $name . '"';
    }

    /** Wrap a search term for a "contains" LIKE query (values are always bound, never interpolated). */
    public static function like(string $term): string
    {
        return '%' . $term . '%';
    }
}
