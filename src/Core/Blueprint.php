<?php

declare(strict_types=1);

namespace App\Core;

final class Blueprint
{
    public array $columns = [];
    public array $foreign = [];
    public array $indexes = [];
    public array $unique = [];

    public function id(): self
    {
        return $this->add('id', 'id');
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->add($name, 'string', ['length' => $length]);
    }

    public function text(string $name): self
    {
        return $this->add($name, 'text');
    }

    public function integer(string $name): self
    {
        return $this->add($name, 'integer');
    }

    public function boolean(string $name): self
    {
        return $this->add($name, 'boolean');
    }

    public function decimal(string $name): self
    {
        return $this->add($name, 'decimal');
    }

    public function date(string $name): self
    {
        return $this->add($name, 'date');
    }

    public function datetime(string $name): self
    {
        return $this->add($name, 'datetime');
    }

    /** A foreign key column. $onDelete is CASCADE, SET NULL or RESTRICT. */
    public function foreignId(string $name, string $references, string $onDelete = 'CASCADE'): self
    {
        $this->foreign[] = [$name, $references, $onDelete];
        $this->indexes[] = [$name];

        return $this->add($name, 'foreign');
    }

    public function timestamps(): void
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
    }

    public function nullable(): self
    {
        $this->columns[array_key_last($this->columns)]['nullable'] = true;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->columns[array_key_last($this->columns)]['default'] = $value;

        return $this;
    }

    public function index(string ...$columns): self
    {
        $this->indexes[] = $columns;

        return $this;
    }

    public function unique(string ...$columns): self
    {
        $this->unique[] = $columns;

        return $this;
    }

    private function add(string $name, string $type, array $extra = []): self
    {
        $this->columns[] = ['name' => $name, 'type' => $type, 'nullable' => false] + $extra;

        return $this;
    }
}
