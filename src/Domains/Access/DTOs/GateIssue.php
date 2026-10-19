<?php

namespace LaRecipe\Domains\Access\DTOs;

/**
 * A problem with a page's gate blocks, in the shape larecipe:validate reports:
 * a stable rule id, a level, a message and, where possible, a fix an agent can apply.
 */
final readonly class GateIssue
{
    public const UNKNOWN_ABILITY = 'access.unknown-ability';

    public const REDUNDANT_GATE = 'access.redundant-gate';

    public function __construct(
        public string $rule,
        public string $level,
        public string $ability,
        public string $message,
        public ?string $fix = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'level' => $this->level,
            'rule' => $this->rule,
            'ability' => $this->ability,
            'message' => $this->message,
            'fix' => $this->fix,
        ], fn ($value) => $value !== null);
    }
}
