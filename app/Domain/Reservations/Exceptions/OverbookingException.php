<?php

namespace App\Domain\Reservations\Exceptions;

use App\Domain\Reservations\DTOs\ConflictReport;
use RuntimeException;

class OverbookingException extends RuntimeException
{
    /** @param list<ConflictReport> $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct(implode(' ', array_map(fn (ConflictReport $c) => $c->message, $conflicts)));
    }

    public function toArray(): array
    {
        return array_map(fn (ConflictReport $c) => $c->toArray(), $this->conflicts);
    }
}
