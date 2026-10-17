<?php

namespace LaRecipe\Domains\Admin\Queries;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\ListConflictsAction;
use LaRecipe\Domains\Sources\Actions\ListDraftsAction;
use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * Everything waiting at the pass for one set: docs-migration conflicts first
 * (they hold a deploy's change back), then drafts, each oldest first.
 */
final class PendingRevisionsQuery
{
    public function __construct(
        private readonly ListConflictsAction $conflicts,
        private readonly ListDraftsAction $drafts,
    ) {}

    /** @return list<Revision> */
    public function get(string $set, ?string $version = null): array
    {
        return [...$this->conflicts->execute($set, $version), ...$this->drafts->execute($set, $version)];
    }

    /** @return list<Revision> the pending changes to one page (at its path, or moving away from it) */
    public function forPage(PageRef $ref): array
    {
        return array_values(array_filter(
            $this->get($ref->set, $ref->version),
            fn (Revision $revision) => $revision->ref?->key() === $ref->key() || $revision->refBefore?->key() === $ref->key(),
        ));
    }
}
