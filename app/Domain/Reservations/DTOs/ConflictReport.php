<?php

namespace App\Domain\Reservations\DTOs;

final class ConflictReport
{
    public const OK = 'ok';

    public const TENTATIVE = 'tentative'; // fits, but only if competing quotes do not convert

    public const SHORTAGE = 'shortage';

    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $requested,
        public readonly int $available,
        public readonly int $tentative,
        public readonly string $level,
        public readonly string $message,
        public readonly array $competing = [],
    ) {}

    public function hasConflict(): bool
    {
        return $this->level === self::SHORTAGE;
    }

    public function shortage(): int
    {
        return max(0, $this->requested - $this->available);
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product' => $this->productName,
            'requested' => $this->requested,
            'available' => $this->available,
            'tentative' => $this->tentative,
            'shortage' => $this->shortage(),
            'level' => $this->level,
            'message' => $this->message,
            'competing' => $this->competing,
        ];
    }
}
