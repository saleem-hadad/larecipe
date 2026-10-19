<?php

namespace LaRecipe\Domains\Sources\Exceptions;

use RuntimeException;

/**
 * A docs migration whose content is invalid (an empty page, a broken address).
 * It fails the run on purpose: CI applies docs migrations, so the PR fails
 * before the change reaches a deploy. Conflicts never throw; they become drafts.
 */
final class DocsMigrationFailed extends RuntimeException
{
    public static function because(string $migration, string $reason): self
    {
        return new self("Docs migration [{$migration}] failed: {$reason}");
    }
}
