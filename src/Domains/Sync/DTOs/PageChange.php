<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * One write the docs agent made (or, in a dry run, would have made) through
 * an author tool, as the tool reported it.
 */
final readonly class PageChange
{
    public const PLANNED = 'planned';

    public const REFUSED = 'refused';

    /**
     * @param  string  $tool  write_page, move_page, delete_page, update_navigation or update_manifest
     * @param  string  $subject  the page path, or "navigation" and "manifest"
     * @param  string  $outcome  the tool's outcome (published, drafted, unchanged, conflicted, rejected), or planned and refused
     * @param  ?string  $summary  the agent's one line for the change
     * @param  ?string  $docsMigration  the docs migration file the change went into
     */
    public function __construct(
        public string $tool,
        public string $subject,
        public string $outcome,
        public ?string $summary = null,
        public ?string $message = null,
        public ?string $docsMigration = null,
        public ?string $movedTo = null,
    ) {}

    /** The change happened (or would have, in a dry run). */
    public function ok(): bool
    {
        return in_array($this->outcome, ['published', 'drafted', self::PLANNED], true);
    }

    public function isPage(): bool
    {
        return in_array($this->tool, ['write_page', 'move_page', 'delete_page'], true);
    }
}
