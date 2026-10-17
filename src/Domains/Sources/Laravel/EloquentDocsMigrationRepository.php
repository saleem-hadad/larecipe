<?php

namespace LaRecipe\Domains\Sources\Laravel;

use LaRecipe\Domains\Sources\Contracts\DocsMigrationRepository;
use LaRecipe\Domains\Sources\DTOs\AppliedMigration;
use LaRecipe\Domains\Sources\DTOs\AppliedOperation;
use LaRecipe\Support\Contracts\Clock;

/**
 * Docs migration bookkeeping in the larecipe_docs_migrations table, through
 * the configurable DocsMigrationRecord model.
 */
final class EloquentDocsMigrationRepository implements DocsMigrationRepository
{
    public function __construct(
        private readonly SourceModels $models,
        private readonly Clock $clock,
    ) {}

    public function append(string $migration, string $set, array $operations): void
    {
        $record = $this->models->query('docs_migration')->where('migration', $migration)->first()
            ?? $this->models->make('docs_migration')->fill(['migration' => $migration, 'set' => $set, 'operations' => [], 'conflicts' => []]);

        $all = [...($record->operations ?? []), ...array_map(fn (AppliedOperation $operation) => $operation->toArray(), $operations)];

        $record->fill([
            'set' => $set,
            'operations' => $all,
            'conflicts' => array_values(array_filter($all, fn (array $operation) => $operation['reason'] !== null)),
            'applied_at' => $this->clock->now(),
        ])->save();
    }

    public function find(string $migration): ?AppliedMigration
    {
        $record = $this->models->query('docs_migration')->where('migration', $migration)->first();

        if ($record === null) {
            return null;
        }

        return new AppliedMigration(
            $record->migration,
            $record->set,
            array_map(fn (array $operation) => AppliedOperation::fromArray($operation), $record->operations ?? []),
        );
    }

    public function forget(string $migration): void
    {
        $this->models->query('docs_migration')->where('migration', $migration)->delete();
    }
}
