<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Domains\Validation\Contracts\LinkTargets;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;

/**
 * Link targets for a whole-set run: every page and its anchors are already in
 * the snapshot, so no page is read twice.
 */
final class SnapshotLinkTargets implements LinkTargets
{
    public function __construct(private readonly DocsSnapshot $docs) {}

    public function anchors(string $version, string $locale, string $path): ?array
    {
        return $this->page($version, $locale, $path)?->anchors;
    }

    public function isPrivate(string $version, string $locale, string $path): bool
    {
        $snapshot = $this->docs->version($version, $locale);
        $page = $this->page($version, $locale, $path);

        return $snapshot !== null && ($snapshot->private || ($page !== null && $page->page->frontmatter->visibility()->value === 'private'));
    }

    public function paths(string $version, string $locale): array
    {
        return array_keys(($this->docs->version($version, $locale)?->pages ?? []) + ($this->docs->original($version)?->pages ?? []));
    }

    /** The page a reader gets at a path: the translation, or the original until there is one. */
    private function page(string $version, string $locale, string $path): ?PageSnapshot
    {
        return $this->docs->version($version, $locale)?->pages[$path] ?? $this->docs->original($version)?->pages[$path] ?? null;
    }
}
