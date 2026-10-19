<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * What applying (or rolling back) a docs migration did, including the
 * warnings `php artisan migrate` prints for conflicts.
 */
final readonly class MigrationOutcome
{
    /**
     * @param  list<AppliedOperation>  $operations
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $name,
        public string $set,
        public array $operations = [],
        public array $warnings = [],
    ) {}

    public function hasConflicts(): bool
    {
        return $this->warnings !== [];
    }
}
