<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * One problem in the docs, with a stable rule id agents can act on and, where
 * possible, a fix they can apply.
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
        public ?string $set = null,
        public ?string $file = null,
    ) {}

    public static function error(string $rule, string $message, ?string $page = null, ?int $line = null, ?string $fix = null, ?string $file = null): self
    {
        return new self(self::ERROR, $rule, $message, $page, $line, $fix, null, $file);
    }

    public static function warning(string $rule, string $message, ?string $page = null, ?int $line = null, ?string $fix = null, ?string $file = null): self
    {
        return new self(self::WARNING, $rule, $message, $page, $line, $fix, null, $file);
    }

    public function inSet(string $set): self
    {
        return new self($this->level, $this->rule, $this->message, $this->page, $this->line, $this->fix, $set, $this->file);
    }

    /** The same issue as a warning: link problems before a write, when the target may be written next. */
    public function asWarning(): self
    {
        return new self(self::WARNING, $this->rule, $this->message, $this->page, $this->line, $this->fix, $this->set, $this->file);
    }

    public function toArray(): array
    {
        return array_filter([
            'level' => $this->level,
            'rule' => $this->rule,
            'set' => $this->set,
            'page' => $this->page,
            'file' => $this->file,
            'line' => $this->line,
            'message' => $this->message,
            'fix' => $this->fix,
        ], fn ($value) => $value !== null);
    }
}
