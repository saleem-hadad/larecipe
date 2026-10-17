<?php

namespace LaRecipe\Domains\Sources\Queries;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * Drafts waiting for a reviewer (not conflicts), oldest first.
 */
final class DraftsQuery
{
    public function __construct(private readonly WritableSources $sources) {}

    /** @return list<Revision> */
    public function get(string $set, ?string $version = null): array
    {
        $drafts = [];

        foreach ($this->sources->draftable($set)->drafts($version) as $revision) {
            if ($revision->is(RevisionStatus::Draft)) {
                $drafts[] = $revision;
            }
        }

        return $drafts;
    }
}
