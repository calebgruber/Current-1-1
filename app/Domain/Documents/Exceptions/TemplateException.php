<?php

namespace App\Domain\Documents\Exceptions;

use RuntimeException;

class TemplateException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $templateLine = null)
    {
        parent::__construct($templateLine ? "Line {$templateLine}: {$message}" : $message);
    }
}
