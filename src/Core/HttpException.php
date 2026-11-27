<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : match ($status) {
            403 => 'You do not have permission to do that.',
            404 => 'We could not find that page.',
            419 => 'Your session expired. Please go back and try again.',
            default => 'Something went wrong.',
        });
    }
}
