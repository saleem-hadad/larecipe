<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Domains\Validation\Contracts\LinkTargets;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;

/**
 * Link targets for a whole-set run: every page and its anchors are already in
 * the snapshot, so no page is read twice.
 */
final class SnapshotLinkTargets implements LinkTargets
{
    public function __construct(private readonly DocsSnapshot $docs) {}

    public function anchors(string $version, string $locale, string $path): ?array
    {
        return $this->docs->version($version, $locale)?->pages[$path]->anchors ?? null;
    }

    public function isPrivate(string $version, string $locale, string $path): bool
    {
        $snapshot = $this->docs->version($version, $locale);
        $page = $snapshot?->pages[$path] ?? null;

        return $snapshot !== null && ($snapshot->private || ($page !== null && $page->page->frontmatter->visibility()->value === 'private'));
    }

    public function paths(string $version, string $locale): array
    {
        return array_keys($this->docs->version($version, $locale)?->pages ?? []);
    }
}
