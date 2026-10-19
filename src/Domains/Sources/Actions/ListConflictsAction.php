<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Queries\ConflictsQuery;

/**
 * Open docs-migration conflicts, for the top of the admin review queue,
 * larecipe:validate and larecipe:doctor.
 */
final class ListConflictsAction
{
    public function __construct(private readonly ConflictsQuery $conflicts) {}

    /** @return list<Revision> */
    public function execute(string $set, ?string $version = null): array
    {
        return $this->conflicts->get($set, $version);
    }
}
