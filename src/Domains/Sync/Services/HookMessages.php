<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\AffectedPage;
use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\Enums\AffectedReason;
use LaRecipe\Domains\Sync\Enums\ReviewMode;

/**
 * The words the pre-push check and SessionStart say to the agent.
 *
 * They are short on purpose: the agent reads them mid-task, and a block it
 * cannot act on in two lines is a block it will argue with. The next step
 * follows the review mode, the same way the AGENTS.md instructions do.
 */
final class HookMessages
{
    public const INDEX_HINT = 'Pages were added, removed or retitled: run php artisan larecipe:agents-md so the docs index in AGENTS.md stays current.';

    /**
     * @param  list<AffectedPage>  $stale
     * @param  array<string, CatalogSet>  $sets  by key
     */
    public function denyReason(array $stale, array $sets, bool $indexStale): string
    {
        $count = count($stale);
        $lines = [$count === 1
            ? 'LaRecipe: this push changes code that 1 docs page describes, and it was not updated:'
            : "LaRecipe: this push changes code that {$count} docs pages describe, and they were not updated:"];

        $labels = array_map(fn (AffectedPage $page) => $page->page->label(), $stale);
        $width = max(array_map('strlen', $labels));

        foreach ($stale as $index => $page) {
            $lines[] = '  - '.str_pad($labels[$index], $width).'   ('.$this->why($page).')';
        }

        $pageSets = array_values(array_filter(array_map(fn (AffectedPage $page) => $sets[$page->page->ref->set] ?? null, $stale)));
        $database = array_values(array_filter($pageSets, fn (CatalogSet $set) => $set->isDatabase()));
        $required = $pageSets === [] || array_filter($pageSets, fn (CatalogSet $set) => $set->review === ReviewMode::Required) !== [];
        $them = $count === 1 ? 'it' : 'them';

        if ($database === []) {
            $lines[] = "Update {$them} with the larecipe-update-docs skill, run php artisan larecipe:validate,";
        } else {
            $migrations = rtrim($database[0]->migrationsPath, '/').'/';
            $lines[] = "Update {$them} with the larecipe-update-docs skill (database pages through the LaRecipe MCP tools, which write the branch's docs migration in {$migrations}), run php artisan larecipe:validate,";
        }

        $lines[] = $required
            ? 'show the user the docs diff and wait for approval, then commit and push again.'
            : 'then commit and push again.';
        $lines[] = 'If no docs change is needed, tell the user why and push again; the next push is allowed.';

        if ($indexStale) {
            $lines[] = self::INDEX_HINT;
        }

        return implode("\n", $lines);
    }

    /**
     * The compact map SessionStart injects, a few hundred tokens at most.
     *
     * @param  array<string, list<CatalogPage>>  $pagesBySet
     * @param  list<CatalogSet>  $sets
     */
    public function sessionContext(array $sets, array $pagesBySet, int $driftCount, string $defaultBranch, int $maxLength = 1600): string
    {
        $lines = [];

        foreach ($sets as $set) {
            $pages = array_values(array_filter($pagesBySet[$set->key] ?? [], fn (CatalogPage $page) => ! $page->hidden));
            $version = $set->versioned ? ", version {$set->defaultVersion}" : '';
            $lines[] = "LaRecipe docs: set `{$set->key}` in ".$set->location()." ({$set->driver}{$version}, ".count($pages).' pages, review mode '.$set->review->value.').';
            $lines[] = 'Map: '.$this->map($pages);
        }

        if ($driftCount > 0) {
            $lines[] = $driftCount === 1
                ? 'Stale: 1 page documents code that changed after the page was last updated (php artisan larecipe:affected lists it).'
                : "Stale: {$driftCount} pages document code that changed after they were last updated (php artisan larecipe:affected lists them).";
        }

        $lines[] = "Before you push or open a pull request: run php artisan larecipe:affected --uncommitted --base={$defaultBranch} and update the pages it lists with the larecipe-update-docs skill. Search and read the docs with the larecipe MCP server.";

        $text = implode("\n", $lines);

        return strlen($text) > $maxLength ? rtrim(substr($text, 0, $maxLength - 3)).'...' : $text;
    }

    private function why(AffectedPage $page): string
    {
        if ($page->reason === AffectedReason::Translation) {
            return 'source page: '.implode(', ', $page->matchedSources);
        }

        return 'sources: '.implode(', ', $page->matchedSources);
    }

    /**
     * Pages grouped by folder: "index, installation, guides/{webhooks, retries}".
     *
     * @param  list<CatalogPage>  $pages
     */
    private function map(array $pages): string
    {
        $groups = [];

        foreach ($pages as $page) {
            $path = $page->ref->path;
            $folder = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/')) : '';
            $groups[$folder][] = $folder === '' ? $path : substr($path, strlen($folder) + 1);
        }

        ksort($groups);
        $parts = [];

        foreach ($groups as $folder => $names) {
            sort($names);
            $parts[] = $folder === '' ? implode(', ', $names) : $folder.'/{'.implode(', ', $names).'}';
        }

        return $parts === [] ? '(no pages yet)' : implode(', ', $parts);
    }
}
