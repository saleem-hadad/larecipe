<?php

namespace LaRecipe\Domains\Sources\Events;

use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * A docs migration found production changed since the migration was written,
 * so it did not overwrite: its version waits as a conflict draft next to
 * production's (revision is null when there is nothing to hold, such as a move
 * of a page production already deleted). The deploy carries on.
 */
final readonly class DocsMigrationConflicted
{
    public function __construct(
        public string $set,
        public string $migration,
        public string $reason,
        public ?Revision $revision = null,
    ) {}
}
