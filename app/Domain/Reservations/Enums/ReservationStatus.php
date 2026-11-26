<?php

namespace App\Domain\Reservations\Enums;

enum ReservationStatus: string
{
    case Tentative = 'tentative';
    case Confirmed = 'confirmed';
    case Released = 'released';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
