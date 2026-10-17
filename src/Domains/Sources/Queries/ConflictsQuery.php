<?php

namespace LaRecipe\Domains\Sources\Queries;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * Docs-migration conflicts still waiting for a decision. They lead the review
 * queue: each holds the migration's version and production's side by side.
 */
final class ConflictsQuery
{
    public function __construct(private readonly WritableSources $sources) {}

    /** @return list<Revision> */
    public function get(string $set, ?string $version = null): array
    {
        $conflicts = [];

        foreach ($this->sources->draftable($set)->drafts($version) as $revision) {
            if ($revision->is(RevisionStatus::Conflict)) {
                $conflicts[] = $revision;
            }
        }

        return $conflicts;
    }
}
