<?php

namespace LaRecipe\Domains\References\Actions;

use LaRecipe\Domains\References\Markdown\ReferenceComponents;
use LaRecipe\Domains\References\Services\ReferenceRegistry;

/**
 * One fingerprint for the parts of the app a page's live references read. It is
 * part of the render cache key of pages that use references, so a renamed route,
 * a new column or a changed command signature re-renders exactly those pages.
 */
final class ReferencesFingerprintAction
{
    public function __construct(private readonly ReferenceRegistry $registry) {}

    /** @param list<string>|null $components component names used on the page (RenderedPage::$components), or null for every kind */
    public function execute(?array $components = null): string
    {
        $kinds = $components === null
            ? array_keys($this->registry->all())
            : array_values(array_filter(array_map(fn (string $name) => ReferenceComponents::kindOf($name), $components)));

        sort($kinds);
        $parts = [];

        foreach (array_unique($kinds) as $kind) {
            $parts[] = $kind.'='.($this->registry->get($kind)?->fingerprint() ?? 'none');
        }

        return substr(hash('xxh128', implode('|', $parts)), 0, 16);
    }
}
