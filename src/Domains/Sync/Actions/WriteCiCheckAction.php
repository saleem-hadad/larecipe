<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\Enums\CiMode;
use LaRecipe\Support\Paths;

/**
 * Writes the optional no-AI CI check (L3, ARCHITECTURE.md 14.4) to
 * .github/workflows/larecipe-docs-check.yml, from stubs/larecipe-docs-check.yml.
 *
 * It runs larecipe:validate and larecipe:affected on pull requests, and in
 * either mode it only reads: no AI agent, no commits, no pushes, and no
 * secrets for repository sets, so it is safe on forks. An existing workflow
 * that differs from what LaRecipe would write is left alone.
 */
final class WriteCiCheckAction
{
    public const PATH = '.github/workflows/larecipe-docs-check.yml';

    public function __construct(private readonly DocsCatalog $catalog) {}

    public function execute(Workspace $workspace, CiMode $mode, string $php = '8.4'): FileChange
    {
        $contents = $this->render($mode, $php);
        $current = $workspace->read(self::PATH);

        if ($current !== null && $current !== $contents) {
            return $workspace->skip(self::PATH, 'A docs check workflow already exists and LaRecipe never overwrites it. Delete it to write a fresh one.');
        }

        return $workspace->write(self::PATH, $contents);
    }

    private function render(CiMode $mode, string $php): string
    {
        $database = array_values(array_filter($this->catalog->sets(), fn (CatalogSet $set) => $set->isDatabase()));

        return strtr($this->stub('larecipe-docs-check.yml'), [
            '%%mode_note%%' => $mode === CiMode::Fail
                ? 'Mode: fail. Add the skip-docs label to a pull request that needs no docs change.'
                : 'Mode: comment. The check posts the list of stale pages and never fails.',
            '%%permissions%%' => $mode === CiMode::Comment ? "  pull-requests: write   # to post the comment, nothing else\n" : '',
            '%%php%%' => $php,
            '%%database%%' => $database === [] ? '' : strtr($this->stub('ci/database.yml'), [
                '%%sets%%' => implode(', ', array_map(fn (CatalogSet $set) => $set->key, $database)),
                '%%set%%' => $database[0]->key,
            ]),
            '%%affected%%' => rtrim($this->stub($mode === CiMode::Fail ? 'ci/affected-fail.yml' : 'ci/affected-comment.yml'), "\n"),
        ]);
    }

    private function stub(string $name): string
    {
        return (string) file_get_contents(Paths::stubs($name));
    }
}
