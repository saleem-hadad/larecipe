<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Services\HookMessages;
use LaRecipe\Domains\Sync\Services\PushRange;

/**
 * What an agent learns about the docs when a session starts: where they
 * live, a compact map of the pages, how many have drifted, and the
 * before-you-push rule. A few hundred tokens, so every session starts
 * knowing the docs exist without paying for them.
 */
final class BuildSessionContextAction
{
    /** Past this many pages with sources, counting drift would slow the session start. */
    private const DRIFT_PAGE_LIMIT = 200;

    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly FindAffectedPagesAction $affected,
        private readonly Vcs $vcs,
        private readonly PushRange $range,
        private readonly HookMessages $messages,
        private readonly SyncSettings $settings,
    ) {}

    public function execute(): string
    {
        $sets = $this->catalog->sets();
        usort($sets, fn (CatalogSet $a, CatalogSet $b) => ($b->key === $this->settings->defaultSet) <=> ($a->key === $this->settings->defaultSet));

        $pages = [];
        $withSources = 0;

        foreach ($sets as $set) {
            $pages[$set->key] = $this->catalog->pages($set->key, $set->defaultVersion, $set->defaultLocale);

            foreach ($pages[$set->key] as $page) {
                $withSources += $page->sources === [] ? 0 : 1;
            }
        }

        $drift = 0;

        if ($this->vcs->available() && $withSources > 0 && $withSources <= self::DRIFT_PAGE_LIMIT) {
            $drift = count($this->affected->execute(new AffectedQuery(uncommitted: true, drift: true))->drift);
        }

        return $this->messages->sessionContext($sets, $pages, $drift, $this->range->defaultBranch());
    }
}
