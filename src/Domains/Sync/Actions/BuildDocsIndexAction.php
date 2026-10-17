<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Services\DocsIndexRenderer;

/**
 * The compact docs index for AGENTS.md: one line per page of a set's default
 * version, within the configured byte budget.
 *
 * Agents evaluated with a small always-loaded docs map do better than with
 * skills alone (ARCHITECTURE.md 13.4), and an app's internal docs are
 * invisible to every other docs search, so this map is what tells a session
 * the docs exist and what they cover.
 */
final class BuildDocsIndexAction
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly DocsIndexRenderer $renderer,
        private readonly SyncSettings $settings,
    ) {}

    /** Null when the set does not exist. */
    public function execute(?string $set = null): ?string
    {
        $catalogSet = $this->catalog->set($set ?? $this->settings->defaultSet) ?? ($set === null ? ($this->catalog->sets()[0] ?? null) : null);

        if ($catalogSet === null) {
            return null;
        }

        $pages = $this->catalog->pages($catalogSet->key, $catalogSet->defaultVersion, $catalogSet->defaultLocale);

        return $this->renderer->render($catalogSet, $pages, $this->settings->indexMaxBytes);
    }
}
