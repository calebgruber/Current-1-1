<?php

namespace App\Domain\Inventory\DTOs;

/**
 * Explains *why* a quantity is allocated: the job, reservation, maintenance
 * record, asset or location responsible. Every number in the Availability
 * Explorer is backed by a list of these so it can be drilled into.
 */
final class AllocationSource
{
    public function __construct(
        public readonly string $type,
        public readonly int|string|null $id,
        public readonly string $label,
        public readonly int $quantity,
        public readonly ?string $url = null,
        public readonly ?string $startsAt = null,
        public readonly ?string $endsAt = null,
        public readonly array $meta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'quantity' => $this->quantity,
            'url' => $this->url,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'meta' => $this->meta,
        ];
    }
}
