<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Queries\RevisionHistoryQuery;

/**
 * One revision by id, with its full payload (and production's side, for a
 * conflict), for the admin UI's diff and resolve views.
 */
final class GetRevisionAction
{
    public function __construct(private readonly RevisionHistoryQuery $history) {}

    public function execute(string $set, string $id): ?Revision
    {
        return $this->history->find($set, $id);
    }
}
