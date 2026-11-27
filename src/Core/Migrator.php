<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Runs the migration files in database/migrations. Used by the setup wizard and
 * by Admin › Database so no command line is ever needed.
 */
final class Migrator
{
    public function __construct(private readonly Database $db, private readonly string $path = BASE_PATH . '/database/migrations')
    {
    }

    /** @return array<int, array{name: string, ran: bool, batch: ?int, ran_at: ?string}> */
    public function status(): array
    {
        $ran = [];
        if ($this->db->tableExists('migrations')) {
            foreach ($this->db->all('SELECT migration, batch, ran_at FROM migrations') as $row) {
                $ran[$row['migration']] = $row;
            }
        }

        return array_map(static fn (string $name) => [
            'name' => $name,
            'ran' => isset($ran[$name]),
            'batch' => isset($ran[$name]) ? (int) $ran[$name]['batch'] : null,
            'ran_at' => $ran[$name]['ran_at'] ?? null,
        ], $this->files());
    }

    /** @return string[] names of pending migrations */
    public function pending(): array
    {
        return array_values(array_map(
            static fn ($m) => $m['name'],
            array_filter($this->status(), static fn ($m) => ! $m['ran']),
        ));
    }

    /** Run every pending migration. Returns the names that ran. */
    public function migrate(): array
    {
        $this->ensureTable();
        $pending = $this->pending();
        if ($pending === []) {
            return [];
        }
        $batch = (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;
        $schema = new Schema($this->db);

        foreach ($pending as $name) {
            $migration = require $this->path . '/' . $name . '.php';
            // MySQL commits DDL implicitly, so each migration is recorded straight after it runs.
            ($migration['up'])($schema, $this->db);
            $this->db->insert('migrations', ['migration' => $name, 'batch' => $batch, 'ran_at' => now()]);
        }

        return $pending;
    }

    /** Roll back the most recent batch. Returns the names rolled back. */
    public function rollback(): array
    {
        if (! $this->db->tableExists('migrations')) {
            return [];
        }
        $batch = (int) $this->db->value('SELECT MAX(batch) FROM migrations');
        $names = array_column(
            $this->db->all('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC', [$batch]),
            'migration',
        );
        $schema = new Schema($this->db);
        foreach ($names as $name) {
            $file = $this->path . '/' . $name . '.php';
            if (is_file($file)) {
                $migration = require $file;
                ($migration['down'])($schema, $this->db);
            }
            $this->db->run('DELETE FROM migrations WHERE migration = ?', [$name]);
        }

        return $names;
    }

    private function ensureTable(): void
    {
        if ($this->db->tableExists('migrations')) {
            return;
        }
        (new Schema($this->db))->create('migrations', function (Blueprint $t) {
            $t->id();
            $t->string('migration');
            $t->integer('batch');
            $t->datetime('ran_at')->nullable();
        });
    }

    /** @return string[] */
    private function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);

        return array_map(static fn ($f) => basename($f, '.php'), $files);
    }
}
