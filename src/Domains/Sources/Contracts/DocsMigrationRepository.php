<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Sources\DTOs\AppliedOperation;

/**
 * The bookkeeping of applied docs migrations (larecipe_docs_migrations): which
 * revision each operation created and which one it replaced, so `down()` can
 * put things back, and which operations became conflicts.
 */
interface DocsMigrationRepository
{
    /** @param list<AppliedOperation> $operations */
    public function append(string $migration, string $set, array $operations): void;

    /** @return list<AppliedOperation> in the order they were applied */
    public function operations(string $migration): array;

    public function has(string $migration): bool;

    public function forget(string $migration): void;
}
