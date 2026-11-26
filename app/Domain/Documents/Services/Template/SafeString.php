<?php

namespace App\Domain\Documents\Services\Template;

/** Marks trusted HTML; attributeText is the original text only when produced by an escaping filter. */
final class SafeString implements \Stringable
{
    public function __construct(
        public readonly string $html,
        public readonly ?string $attributeText = null,
    ) {}

    public function __toString(): string
    {
        return $this->html;
    }
}
