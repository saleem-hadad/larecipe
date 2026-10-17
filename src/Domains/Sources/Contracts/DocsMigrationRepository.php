<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Sources\DTOs\AppliedMigration;
use LaRecipe\Domains\Sources\DTOs\AppliedOperation;

/**
 * The bookkeeping of applied docs migrations (larecipe_docs_migrations): which
 * revision each operation created and which one it replaced, so `down()` can
 * put things back, and which operations became conflicts.
 */
interface DocsMigrationRepository
{
    /** @param list<AppliedOperation> $operations appended after what the migration already recorded */
    public function append(string $migration, string $set, array $operations): void;

    public function find(string $migration): ?AppliedMigration;

    public function forget(string $migration): void;
}
