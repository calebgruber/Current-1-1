<?php

namespace App\Domain\Documents\Services\Template;

/** Marks already-escaped/trusted HTML so it is not escaped again. */
final class SafeString implements \Stringable
{
    public function __construct(public readonly string $html) {}

    public function __toString(): string
    {
        return $this->html;
    }
}
