<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Onboarding\Services\FrontmatterWriter;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * Creates one page file in a repository set, with the frontmatter every page
 * should have: a title, a description and the code it documents. Existing
 * pages are never replaced.
 */
final class MakePageAction
{
    public function __construct(private readonly FrontmatterWriter $frontmatter) {}

    /** @param list<string> $sources */
    public function execute(Workspace $workspace, CatalogSet $set, string $path, string $version, string $locale, string $title, ?string $description = null, array $sources = []): FileChange
    {
        $path = PageRef::normalizePath($path);
        $file = $this->file($set, $path, $version, $locale);

        if ($workspace->exists($file) || $workspace->exists(substr($file, 0, -3).'/index.md')) {
            return $workspace->skip($file, 'That page already exists.');
        }

        $contents = $this->frontmatter->render([
            'title' => $title,
            'description' => $description,
            'sources' => array_values($sources),
        ])."\n# {$title}\n";

        return $workspace->write($file, $contents);
    }

    private function file(CatalogSet $set, string $path, string $version, string $locale): string
    {
        $parts = [rtrim((string) $set->docsPath, '/')];

        if ($set->versioned) {
            $parts[] = $version;
        }

        if ($locale !== $set->defaultLocale) {
            $parts[] = $locale;
        }

        $parts[] = ($path === 'index' ? 'index' : $path).'.md';

        return implode('/', array_filter($parts, fn (string $part) => $part !== ''));
    }
}
