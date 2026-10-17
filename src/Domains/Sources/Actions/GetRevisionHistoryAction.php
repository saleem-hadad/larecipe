<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\Queries\RevisionHistoryQuery;

/**
 * The revision history of a page, a navigation or the manifest, newest first,
 * for the admin UI's history and diff views.
 */
final class GetRevisionHistoryAction
{
    public function __construct(private readonly RevisionHistoryQuery $history) {}

    /** @return list<Revision> */
    public function execute(Subject $subject): array
    {
        return $this->history->get($subject);
    }
}
