<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * One problem in the docs, with a stable rule id agents can act on.
 */
final readonly class ValidationIssue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public function __construct(
        public string $level,
        public string $rule,
        public string $message,
        public ?string $page = null,
        public ?int $line = null,
        public ?string $fix = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'level' => $this->level,
            'rule' => $this->rule,
            'page' => $this->page,
            'line' => $this->line,
            'message' => $this->message,
            'fix' => $this->fix,
        ], fn ($value) => $value !== null);
    }
}
