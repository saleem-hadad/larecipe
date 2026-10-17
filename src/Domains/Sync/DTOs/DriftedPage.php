<?php

namespace LaRecipe\Domains\Sync\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Sync\Enums\AffectedReason;

/**
 * A page that fell behind over time: the code it documents (or, for a
 * translation, its source page) changed after the page was last updated.
 * This is history, independent of the change being checked.
 */
final readonly class DriftedPage
{
    public function __construct(
        public CatalogPage $page,
        public ?DateTimeImmutable $lastUpdated,
        public Commit $staleSince,
        public AffectedReason $reason = AffectedReason::Sources,
    ) {}

    public function toArray(): array
    {
        return [
            ...$this->page->toArray(),
            'reason' => $this->reason->value,
            'last_updated' => $this->lastUpdated?->format(DATE_ATOM),
            'stale_since' => $this->staleSince->toArray(),
        ];
    }
}
