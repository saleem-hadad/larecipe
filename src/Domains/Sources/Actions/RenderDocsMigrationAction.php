<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\Services\DocsMigrationOperations;
use LaRecipe\Domains\Sources\Services\DocsMigrationRenderer;
use LaRecipe\Support\Paths;

/**
 * Writes a docs migration plan as the PHP file a pull request carries, with
 * the same folding as the branch's migration (two updates of one page are
 * one update, against the earliest basedOn) and the same rendering. The
 * admin UI uses it to export changes made in production back into git.
 */
final class RenderDocsMigrationAction
{
    public function __construct(
        private readonly DocsMigrationOperations $operations,
        private readonly DocsMigrationRenderer $renderer,
    ) {}

    public function execute(DocsMigrationPlan $plan): string
    {
        $folded = [];

        foreach ($plan->operations as $operation) {
            $folded = $this->operations->merge($folded, $operation);
        }

        return $this->renderer->render((string) file_get_contents(Paths::stubs('docs-migration.stub')), $plan->withOperations($folded));
    }
}
