<?php

namespace LaRecipe\Domains\Upgrade\DTOs;

/**
 * One line of the upgrade report: something the upgrade changed, dropped, or
 * left for a person (or the larecipe-upgrade-v2 skill) to finish.
 */
final readonly class ReportEntry
{
    /** Converted automatically; nothing to do. */
    public const CHANGED = 'changed';

    /** Dropped on purpose: v3 does it differently or not at all. */
    public const DROPPED = 'dropped';

    /** Needs a person or the larecipe-upgrade-v2 skill. */
    public const MANUAL = 'manual';

    public function __construct(
        public string $kind,
        public string $category,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $fix = null,
    ) {}

    public static function changed(string $category, string $message, ?string $file = null, ?int $line = null): self
    {
        return new self(self::CHANGED, $category, $message, $file, $line);
    }

    public static function dropped(string $category, string $message, ?string $file = null, ?int $line = null, ?string $fix = null): self
    {
        return new self(self::DROPPED, $category, $message, $file, $line, $fix);
    }

    public static function manual(string $category, string $message, ?string $file = null, ?int $line = null, ?string $fix = null): self
    {
        return new self(self::MANUAL, $category, $message, $file, $line, $fix);
    }

    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'category' => $this->category,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'fix' => $this->fix,
        ], fn ($value) => $value !== null);
    }
}
