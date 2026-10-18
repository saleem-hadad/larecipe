<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * A database set's revisions across every page, navigation and the manifest,
 * newest first, a page at a time (older than $before). The admin UI's
 * activity list and its docs-migration export start here.
 */
final class ListRevisionsAction
{
    public function __construct(private readonly WritableSources $sources) {}

    /** @return list<Revision> */
    public function execute(string $set, ?string $before = null, int $limit = 50): array
    {
        $revisions = [];

        foreach ($this->sources->draftable($set)->revisions($before, $limit) as $revision) {
            $revisions[] = $revision;
        }

        return $revisions;
    }
}
