<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\AffectedPage;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\HookDecision;
use LaRecipe\Domains\Sync\DTOs\HookPayload;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Services\HookMessages;
use LaRecipe\Domains\Sync\Services\LoopGuard;
use LaRecipe\Domains\Sync\Services\MarkedBlock;
use LaRecipe\Domains\Sync\Services\PushRange;
use LaRecipe\Domains\Sync\Support\PushCommandDetector;

/**
 * The pre-push check (L2, ARCHITECTURE.md 14.3): decides whether an agent's
 * `git push` or `gh pr create` may run.
 *
 * It looks at everything the branch's pull request will contain (commits
 * since the default branch, plus uncommitted changes), and holds the push
 * once when that change makes docs pages stale. It allows the push when
 * nothing is affected, when every affected page was updated in the same range
 * (its file, or the branch's docs migration for a database set), or when this
 * is the second attempt for the same commit and the same pages: an agent that
 * decided no docs change is needed is never stuck.
 *
 * Deterministic and offline. It never calls an LLM.
 */
final class CheckPrePushAction
{
    public function __construct(
        private readonly FindAffectedPagesAction $affected,
        private readonly BuildDocsIndexAction $index,
        private readonly DocsCatalog $catalog,
        private readonly Vcs $vcs,
        private readonly Workspace $workspace,
        private readonly PushRange $range,
        private readonly LoopGuard $guard,
        private readonly HookMessages $messages,
        private readonly SyncSettings $settings,
    ) {}

    public function execute(HookPayload $payload): HookDecision
    {
        if (! $this->settings->hooksEnabled || ! $this->vcs->available()) {
            return HookDecision::allow();
        }

        // vendor/bin/larecipe-hook already filters, but a hook wired straight to
        // artisan must not run the check on every Bash call either.
        if ($payload->command !== null && ! (new PushCommandDetector($this->settings->pushCommands))->matches($payload->command)) {
            return HookDecision::allow();
        }

        $report = $this->affected->execute(new AffectedQuery(base: $this->range->base(), uncommitted: true, drift: false));
        $stale = $report->stale();
        $indexStale = $this->indexIsStale();

        if ($stale === []) {
            return HookDecision::allow($indexStale ? HookMessages::INDEX_HINT : null);
        }

        $key = LoopGuard::key($this->vcs->headSha(), array_map(fn (AffectedPage $page) => $page->page->label(), $stale));

        if ($this->guard->alreadyHeld($payload->sessionKey(), $key)) {
            return HookDecision::allow();
        }

        $this->guard->remember($payload->sessionKey(), $key);

        $sets = [];

        foreach ($this->catalog->sets() as $set) {
            $sets[$set->key] = $set;
        }

        return HookDecision::deny($this->messages->denyReason($stale, $sets, $indexStale));
    }

    /**
     * Pages were added, removed or retitled since the docs index in AGENTS.md
     * was written. Only checked when the block carries an index.
     */
    private function indexIsStale(): bool
    {
        $block = (new MarkedBlock)->read($this->workspace->read($this->settings->instructionsFile));
        $start = $block === null ? false : strpos($block, '### Docs index');

        if ($start === false) {
            return false;
        }

        $sets = array_map(fn (CatalogSet $set) => $set->key, $this->catalog->sets());
        $indexed = trim(substr((string) $block, $start));

        foreach ($sets as $set) {
            if (trim((string) $this->index->execute($set)) === $indexed) {
                return false;
            }
        }

        return true;
    }
}
