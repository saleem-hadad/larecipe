<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\DTOs\AffectedPage;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\DTOs\AffectedReport;
use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\DriftedPage;
use LaRecipe\Domains\Sync\DTOs\PlannedPage;
use LaRecipe\Domains\Sync\DTOs\SyncPlan;
use LaRecipe\Domains\Sync\DTOs\SyncRequest;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Enums\AffectedReason;
use LaRecipe\Domains\Sync\Enums\SyncMode;
use LaRecipe\Domains\Sync\Enums\SyncTarget;
use LaRecipe\Domains\Sync\Exceptions\SyncRefused;
use LaRecipe\Domains\Sync\Services\PushRange;

/**
 * Decides what a `larecipe:sync` run will do, deterministically and for free,
 * before a single token is spent: the set, the pages the agent works on, the
 * change it reads and where its writes land.
 *
 * The pages come from the same L0 computation every other layer uses
 * (FindAffectedPagesAction), so the agent works on exactly the pages the
 * pre-push hook and `larecipe:affected` would list. The page budget is
 * applied here too, so the confirmation shows what the run will touch.
 */
final class PlanSyncAction
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly Vcs $vcs,
        private readonly PushRange $range,
        private readonly FindAffectedPagesAction $affected,
        private readonly SyncSettings $settings,
    ) {}

    public function execute(SyncRequest $request): SyncPlan
    {
        $set = $this->set($request);
        $version = $this->version($set, $request);
        $locale = $this->locale($set, $request);
        $target = $this->target($set, $request);
        $mode = $request->mode();

        $this->guardCommit($request, $target);

        return match ($mode) {
            SyncMode::Diff => $this->diff($request, $set, $target, $version, $locale),
            SyncMode::Pages => $this->pages($request, $set, $target, $version, $locale),
            SyncMode::Audit => $this->audit($request, $set, $target, $version, $locale),
        };
    }

    private function diff(SyncRequest $request, CatalogSet $set, SyncTarget $target, string $version, string $locale): SyncPlan
    {
        if (! $this->vcs->available()) {
            throw new SyncRefused('larecipe:sync compares your change with the default branch, and this project is not inside a git repository. Name the pages to update with --page, or review the whole set with --audit.');
        }

        $base = $this->base($request);
        $report = $this->report($set, $base, $request->head, $version, $locale, drift: false);
        $planned = array_map(fn (AffectedPage $page) => $this->fromAffected($page), $report->stale());
        $notes = $report->notes;

        if ($report->updated() !== []) {
            $notes[] = count($report->updated()).' affected '.$this->plural(count($report->updated()), 'page was', 'pages were').' already updated by this change, so the agent leaves '.(count($report->updated()) === 1 ? 'it' : 'them').' alone.';
        }

        if ($report->changedFiles === []) {
            $notes[] = $base === null
                ? 'There are no uncommitted changes to compare.'
                : "Nothing changed between {$base} and {$request->head}, and there are no uncommitted changes.";
        }

        return $this->capped($request, $set, SyncMode::Diff, $target, $version, $locale, $planned, $base, $report->changedFiles, $notes);
    }

    private function pages(SyncRequest $request, CatalogSet $set, SyncTarget $target, string $version, string $locale): SyncPlan
    {
        $catalog = $this->catalog->pages($set->key, $version, $locale);
        $base = null;
        $changed = [];
        $affected = [];

        // The change, when there is one, is still useful context for the named pages.
        if ($this->vcs->available()) {
            $base = $this->base($request);
            $report = $this->report($set, $base, $request->head, $version, $locale, drift: false);
            $changed = $report->changedFiles;

            foreach ($report->pages as $page) {
                $affected[$page->page->ref->path] = $page;
            }
        }

        $planned = [];

        foreach (array_values(array_unique($request->pages)) as $asked) {
            $page = $this->find($catalog, $asked, $set);

            if ($page === null) {
                $known = implode(', ', array_slice(array_map(fn (CatalogPage $p) => $p->ref->path, $catalog), 0, 12));

                throw new SyncRefused("The [{$set->key}] set has no page [{$asked}] in version [{$version}]".($known !== '' ? ". Pages include: {$known}." : '.'));
            }

            $match = $affected[$page->ref->path] ?? null;
            $planned[$page->ref->path] = $match !== null
                ? new PlannedPage($page, 'asked for with --page; '.$this->reason($match), $match->changedFiles, $match->matchedSources)
                : new PlannedPage($page, 'asked for with --page');
        }

        return $this->capped($request, $set, SyncMode::Pages, $target, $version, $locale, array_values($planned), $base, $changed, []);
    }

    /**
     * Every page of the version, the ones that drifted first, then the ones
     * without `sources:` (invisible to drift detection), then the rest. The
     * page budget limits what the agent may change, not what it may read.
     */
    private function audit(SyncRequest $request, CatalogSet $set, SyncTarget $target, string $version, string $locale): SyncPlan
    {
        $catalog = $this->catalog->pages($set->key, $version, $locale);
        $drifted = [];
        $notes = [];

        if ($this->vcs->available()) {
            foreach ($this->report($set, null, $request->head, $version, $locale, drift: true)->drift as $drift) {
                $drifted[$drift->page->ref->path] = $drift;
            }
        } else {
            $notes[] = 'This project is not inside a git repository, so the audit cannot see which pages drifted from their code.';
        }

        $first = [];
        $unsourced = [];
        $rest = [];

        foreach ($catalog as $page) {
            match (true) {
                isset($drifted[$page->ref->path]) => $first[] = new PlannedPage($page, $this->driftReason($drifted[$page->ref->path])),
                $page->sources === [] => $unsourced[] = new PlannedPage($page, 'no sources:, so drift detection cannot see it'),
                default => $rest[] = new PlannedPage($page, 'review'),
            };
        }

        if ($catalog === []) {
            $notes[] = "The [{$set->key}] set has no pages in version [{$version}] to audit.";
        }

        return new SyncPlan($request, $set, SyncMode::Audit, $target, $version, $locale, [...$first, ...$unsourced, ...$rest], [], null, [], $notes);
    }

    /**
     * @param  list<PlannedPage>  $planned
     * @param  list<string>  $changed
     * @param  list<string>  $notes
     */
    private function capped(SyncRequest $request, CatalogSet $set, SyncMode $mode, SyncTarget $target, string $version, string $locale, array $planned, ?string $base, array $changed, array $notes): SyncPlan
    {
        $pages = array_slice($planned, 0, $request->maxPages);
        $skipped = array_slice($planned, $request->maxPages);

        if ($skipped !== []) {
            $notes[] = count($skipped).' more '.$this->plural(count($skipped), 'page needs', 'pages need').' an update but '.$this->plural(count($skipped), 'is', 'are').' over the page budget ('.$request->maxPages.'). Run again with --page, or raise --max-pages.';
        }

        return new SyncPlan($request, $set, $mode, $target, $version, $locale, $pages, $skipped, $base, $changed, $notes);
    }

    private function report(CatalogSet $set, ?string $base, string $head, string $version, string $locale, bool $drift): AffectedReport
    {
        return $this->affected->execute(new AffectedQuery(
            set: $set->key,
            base: $base,
            head: $head,
            uncommitted: true,
            version: $version,
            locale: $locale,
            drift: $drift,
        ));
    }

    private function fromAffected(AffectedPage $page): PlannedPage
    {
        return new PlannedPage($page->page, $this->reason($page), $page->changedFiles, $page->matchedSources);
    }

    private function reason(AffectedPage $page): string
    {
        if ($page->reason === AffectedReason::Translation) {
            return 'its source page changed: '.implode(', ', $page->changedFiles);
        }

        return 'sources '.implode(', ', $page->matchedSources).' match '.implode(', ', $page->changedFiles);
    }

    private function driftReason(DriftedPage $drift): string
    {
        return "drifted: its sources changed in {$drift->staleSince->short()} ({$drift->staleSince->subject}) after the page was last updated";
    }

    /** @param list<CatalogPage> $catalog */
    private function find(array $catalog, string $asked, CatalogSet $set): ?CatalogPage
    {
        $asked = trim(str_replace('\\', '/', $asked), '/');
        $docsPath = $set->docsPath !== null && $set->docsPath !== '' ? rtrim($set->docsPath, '/').'/' : null;
        $path = $docsPath !== null && str_starts_with($asked, $docsPath) ? substr($asked, strlen($docsPath)) : $asked;
        $path = preg_replace('/\.md$/i', '', $path) ?? $path;

        foreach ($catalog as $page) {
            if ($page->file === $asked || $page->ref->path === $path || ($path === 'index' && $page->ref->path === 'index')) {
                return $page;
            }
        }

        foreach ($catalog as $page) {
            if ($page->ref->path === preg_replace('#/index$#', '', $path)) {
                return $page;
            }
        }

        return null;
    }

    private function set(SyncRequest $request): CatalogSet
    {
        $key = $request->set ?? $this->settings->defaultSet;
        $set = $this->catalog->set($key);

        if ($set === null) {
            $known = implode(', ', array_map(fn (CatalogSet $s) => $s->key, $this->catalog->sets()));

            throw new SyncRefused("There is no doc set named [{$key}].".($known !== '' ? " Sets: {$known}." : ''));
        }

        return $set;
    }

    private function version(CatalogSet $set, SyncRequest $request): string
    {
        $version = $request->version ?? $set->defaultVersion;

        if (! in_array($version, $set->versions, true)) {
            throw new SyncRefused("The [{$set->key}] set has no version [{$version}]. Versions: ".implode(', ', $set->versions).'.');
        }

        return $version;
    }

    private function locale(CatalogSet $set, SyncRequest $request): string
    {
        $locale = $request->locale ?? $set->defaultLocale;

        if (! in_array($locale, $set->locales, true)) {
            throw new SyncRefused("The [{$set->key}] set has no locale [{$locale}]. Locales: ".implode(', ', $set->locales).'.');
        }

        return $locale;
    }

    private function target(CatalogSet $set, SyncRequest $request): SyncTarget
    {
        if ($request->remote !== null) {
            if (! $set->isDatabase()) {
                throw new SyncRefused("The [{$set->key}] set lives in the repository, so its changes belong in git, not on a remote server. Run without --remote: the agent edits the working tree and you commit the change with your code.");
            }

            if (! preg_match('#^https?://#i', $request->remote)) {
                throw new SyncRefused("--remote takes the MCP endpoint of the production set, for example https://acme.dev/handbook/mcp. [{$request->remote}] is not a URL.");
            }

            return SyncTarget::Remote;
        }

        if (! $set->isDatabase()) {
            return SyncTarget::WorkingTree;
        }

        return $this->vcs->available() ? SyncTarget::DocsMigration : SyncTarget::Database;
    }

    private function guardCommit(SyncRequest $request, SyncTarget $target): void
    {
        if (! $request->commit) {
            return;
        }

        if ($request->dryRun) {
            throw new SyncRefused('A dry run writes nothing, so there is nothing to commit. Drop --commit or --dry-run.');
        }

        if (! $target->ridesInPullRequest() || ! $this->vcs->available()) {
            throw new SyncRefused('--commit commits the docs change on the current branch, and this run writes '.$target->describe().'. Drop --commit.');
        }
    }

    private function base(SyncRequest $request): ?string
    {
        if ($request->base === null) {
            return $this->range->base();
        }

        if (! $this->vcs->refExists($request->base)) {
            throw new SyncRefused("The base [{$request->base}] is not a known branch or commit. Fetch it first (git fetch origin), or pass another --base.");
        }

        return $request->base;
    }

    private function plural(int $count, string $one, string $many): string
    {
        return $count === 1 ? $one : $many;
    }
}
