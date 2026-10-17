<?php

namespace LaRecipe\Domains\Sources\Exceptions;

use RuntimeException;

final class RevisionNotFound extends RuntimeException
{
    public static function withId(string $set, string $id): self
    {
        return new self("The [{$set}] doc set has no revision #{$id}.");
    }

    public static function notPending(string $id, string $status): self
    {
        return new self("Revision #{$id} is {$status}, not a draft or a conflict, so there is nothing to publish or reject.");
    }
}
