<?php

namespace App\Domain\Rules\DTOs;

final class RuleFinding
{
    public function __construct(
        public readonly int|string|null $ruleId,
        public readonly string $rule,
        public readonly string $severity,
        public readonly string $message,
        public readonly int $required = 0,
        public readonly int $found = 0,
        public readonly array $items = [],
        public readonly array $suggestions = [],
    ) {}

    public function missing(): int
    {
        return max(0, $this->required - $this->found);
    }

    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'rule' => $this->rule,
            'severity' => $this->severity,
            'message' => $this->message,
            'required' => $this->required,
            'found' => $this->found,
            'missing' => $this->missing(),
            'items' => $this->items,
            'suggestions' => $this->suggestions,
        ];
    }
}
