<?php

namespace LaRecipe\Domains\Sources\Exceptions;

use RuntimeException;

final class ReadOnlySource extends RuntimeException
{
    public static function forSet(string $set, string $driver): self
    {
        return new self("The [{$set}] doc set uses the [{$driver}] source, which LaRecipe cannot write to.");
    }

    public static function withoutRevisions(string $set): self
    {
        return new self("The [{$set}] doc set keeps no revisions, so it has no drafts to review. Drafts, publishing and reverts exist for database sets; repository sets are reviewed in the pull request.");
    }
}
