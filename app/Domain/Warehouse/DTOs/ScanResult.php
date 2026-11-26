<?php

namespace App\Domain\Warehouse\DTOs;

final class ScanResult
{
    public const SUCCESS = 'success';

    public const WARNING = 'warning';

    public const ERROR = 'error';

    /**
     * @param  list<array<string, mixed>>  $effects  state changes for WarehouseService to apply
     */
    public function __construct(
        public readonly string $level,
        public readonly string $title,
        public readonly string $message,
        public readonly array $effects = [],
        public readonly ?array $line = null,
        public readonly ?array $item = null,
        public readonly ?string $role = null,
    ) {}

    public static function error(string $title, string $message, ?array $item = null): self
    {
        return new self(self::ERROR, $title, $message, [], null, $item);
    }

    public static function warning(string $title, string $message, array $effects = [], ?array $line = null, ?array $item = null): self
    {
        return new self(self::WARNING, $title, $message, $effects, $line, $item);
    }

    public function sound(): string
    {
        return $this->level;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'sound' => $this->sound(),
            'title' => $this->title,
            'message' => $this->message,
            'line' => $this->line,
            'item' => $this->item,
            'role' => $this->role,
        ];
    }
}
