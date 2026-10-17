<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Queries\DraftsQuery;

/**
 * The review queue's drafts (changes made outside a pull request under
 * `review: required`, and drafts people saved), for the admin UI.
 */
final class ListDraftsAction
{
    public function __construct(private readonly DraftsQuery $drafts) {}

    /** @return list<Revision> */
    public function execute(string $set, ?string $version = null): array
    {
        return $this->drafts->get($set, $version);
    }
}
