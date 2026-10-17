<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * What the larecipe_docs_migrations table remembers about one docs migration.
 */
final readonly class AppliedMigration
{
    /** @param list<AppliedOperation> $operations in the order they were applied */
    public function __construct(
        public string $name,
        public string $set,
        public array $operations,
    ) {}

    /** @return list<AppliedOperation> */
    public function conflicts(): array
    {
        return array_values(array_filter($this->operations, fn (AppliedOperation $operation) => $operation->reason !== null));
    }
}
