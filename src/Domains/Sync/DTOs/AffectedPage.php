<?php

namespace LaRecipe\Domains\Sync\DTOs;

use LaRecipe\Domains\Sync\Enums\AffectedReason;
use LaRecipe\Domains\Sync\Enums\AffectedStatus;

/**
 * A page the change touches, and whether the change already updated it.
 *
 * Stale means the code it documents changed in this range and the page did
 * not; updated means the page (or, for a database set, the branch's docs
 * migration) changed too.
 */
final readonly class AffectedPage
{
    /**
     * @param  list<string>  $matchedSources  the `sources:` entries that matched
     * @param  list<string>  $changedFiles  the changed files they matched
     */
    public function __construct(
        public CatalogPage $page,
        public AffectedStatus $status,
        public AffectedReason $reason,
        public array $matchedSources,
        public array $changedFiles,
    ) {}

    public function isStale(): bool
    {
        return $this->status === AffectedStatus::Stale;
    }

    public function toArray(): array
    {
        return [
            ...$this->page->toArray(),
            'status' => $this->status->value,
            'reason' => $this->reason->value,
            'matched_sources' => $this->matchedSources,
            'changed_files' => $this->changedFiles,
        ];
    }
}
