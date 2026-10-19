<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\Enums\ReviewMode;

/**
 * Writes the before-you-push instructions every AI coding session reads
 * (ARCHITECTURE.md 14.2). The same text goes into the AGENTS.md block and the
 * Laravel Boost guideline, so it is generated once, here, from facts: where
 * the docs live, the default branch and the review mode.
 */
final class InstructionsRenderer
{
    /** @param list<CatalogSet> $sets the default set first */
    public function render(array $sets, string $defaultBranch): string
    {
        $database = array_values(array_filter($sets, fn (CatalogSet $set) => $set->isDatabase()));
        $repository = array_values(array_filter($sets, fn (CatalogSet $set) => ! $set->isDatabase()));
        $migrations = $database === [] ? null : '`'.rtrim($database[0]->migrationsPath, '/').'/`';

        $lines = [
            '## Documentation (LaRecipe)',
            '',
            $this->where($sets, $migrations),
            '',
            'Before you push a branch or open a pull request:',
            '',
            "1. Run `php artisan larecipe:affected --uncommitted --base={$defaultBranch}` to list the docs pages your change affects.",
            '2. '.$this->updateStep($repository, $database, $migrations),
            '3. Run `php artisan larecipe:validate` and fix what it reports.',
            '4. '.($repository === [] ? 'Commit the docs migration with the code and push them together.' : ($database === [] ? 'Commit the docs with the code and push them together.' : 'Commit the docs and the docs migration with the code and push them together.')),
            '',
            $this->reviewLine($sets),
            '',
            'Write docs for people first: open each page with the common case in a few short lines, then put options, edge cases and internals after it (in an `<Accordion>` or on a linked page). Order pages from easy to advanced. Agents still read everything in the Markdown twin.',
            '',
            'Search and read the docs with the `larecipe` MCP server. Never write configuration or environment values into docs.',
        ];

        return implode("\n", $lines);
    }

    /** @param list<CatalogSet> $sets */
    public function reviewMode(array $sets): ReviewMode
    {
        foreach ($sets as $set) {
            if ($set->review === ReviewMode::Required) {
                return ReviewMode::Required;
            }
        }

        return $sets === [] ? ReviewMode::Required : ReviewMode::Auto;
    }

    /** @param list<CatalogSet> $sets */
    private function where(array $sets, ?string $migrations): string
    {
        $tail = 'Docs ship in the same push as the code they describe.';

        if (count($sets) <= 1) {
            $set = $sets[0] ?? null;

            if ($set === null || ! $set->isDatabase()) {
                $path = $set?->docsPath !== null && $set?->docsPath !== '' ? rtrim((string) $set?->docsPath, '/') : 'docs';

                return "This app's docs live in `{$path}/` and are served by LaRecipe. {$tail}";
            }

            return "This app's docs live in the database (doc set `{$set->key}`) and are served by LaRecipe. Docs ship in the same push as the code they describe, as docs migrations in {$migrations}.";
        }

        $places = array_map(
            fn (CatalogSet $set) => "`{$set->key}` in ".$set->location(),
            $sets,
        );

        return "This app's docs are served by LaRecipe from ".count($sets).' doc sets: '.$this->sentence($places).". {$tail}";
    }

    /**
     * @param  list<CatalogSet>  $repository
     * @param  list<CatalogSet>  $database
     */
    private function updateStep(array $repository, array $database, ?string $migrations): string
    {
        if ($database === []) {
            return 'Update each affected page with the `larecipe-update-docs` skill. Keep unrelated text untouched.';
        }

        $throughTools = "through the LaRecipe MCP tools; they write a docs migration in {$migrations}; commit it. Never edit docs rows or write SQL by hand.";

        if ($repository === []) {
            return "Update each affected page with the `larecipe-update-docs` skill, {$throughTools} Keep unrelated text untouched.";
        }

        $keys = $this->sentence(array_map(fn (CatalogSet $set) => "`{$set->key}`", $database));

        return "Update each affected page with the `larecipe-update-docs` skill. Keep unrelated text untouched. Pages of {$keys} live in the database: edit them {$throughTools}";
    }

    /** @param list<CatalogSet> $sets */
    private function reviewLine(array $sets): string
    {
        return $this->reviewMode($sets) === ReviewMode::Auto
            ? 'Review mode is `auto`: update the docs and push without asking.'
            : 'Review mode is `required`: show the user the docs diff and wait for approval before committing it.';
    }

    /** @param list<string> $items */
    private function sentence(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
