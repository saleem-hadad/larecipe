<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\AffectedPage;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\DTOs\AffectedReport;
use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\Commit;
use LaRecipe\Domains\Sync\DTOs\DriftedPage;
use LaRecipe\Domains\Sync\DTOs\SourcePattern;
use LaRecipe\Domains\Sync\Enums\AffectedReason;
use LaRecipe\Domains\Sync\Enums\AffectedStatus;
use LaRecipe\Domains\Sync\Services\GlobMatcher;
use LaRecipe\Domains\Sync\Services\RegionLocator;

/**
 * Maps a change to the docs pages it affects. This is L0, the deterministic
 * core every other layer reuses: the AGENTS.md instructions, the pre-push
 * hook, SessionStart, the git hook and the optional CI check all ask this one
 * question, so they always agree about which pages are stale.
 *
 * A page is affected when a changed file matches one of its `sources:`
 * entries (for `path#region`, only when the change touched that region). It
 * is stale when the same range did not also update the page: its Markdown
 * file for a repository set, or a docs migration that names it for a database
 * set. Separately, drift reports pages whose sources changed in commits after
 * the page was last updated, whatever the current change is.
 *
 * No AI, no network: the same inputs always give the same answer.
 */
final class FindAffectedPagesAction
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly Vcs $vcs,
        private readonly Workspace $workspace,
        private readonly GlobMatcher $globs,
        private readonly RegionLocator $regions,
    ) {}

    public function execute(AffectedQuery $query): AffectedReport
    {
        $sets = $this->sets($query);
        $notes = [];

        if (! $this->vcs->available()) {
            return new AffectedReport($query, sets: $sets, notes: ['This project is not inside a git repository, so LaRecipe cannot tell what changed.']);
        }

        if ($query->base !== null && ! $this->vcs->refExists($query->base)) {
            $notes[] = "The base [{$query->base}] is not a known branch or commit. Fetch it first (git fetch origin), or pass another --base.";

            return new AffectedReport($query, sets: $sets, notes: $notes);
        }

        $changed = $this->vcs->changedFiles($query->base, $query->head, $query->includesUncommitted());
        $affected = [];
        $drift = [];

        foreach ($sets as $set) {
            foreach ($this->versions($set, $query, $notes) as $version) {
                $pages = $this->catalog->pages($set->key, $version, $set->defaultLocale);

                if ($pages === [] && $set->isDatabase()) {
                    $notes[] = "Set [{$set->key}] has no pages in the local database. Run php artisan larecipe:pull --set={$set->key} to check it against what is live.";
                }

                $locale = $query->locale ?? $set->defaultLocale;

                if ($locale === $set->defaultLocale) {
                    foreach ($pages as $page) {
                        $affected[] = $match = $this->affected($page, $page->sourcePatterns(), $changed, $set, $query);
                        $drift[] = $query->drift && $match === null ? $this->drift($page, $changed) : null;
                    }

                    continue;
                }

                $twins = [];

                foreach ($pages as $page) {
                    $twins[$page->ref->path] = $page;
                }

                foreach ($this->catalog->pages($set->key, $version, $locale) as $translation) {
                    $twin = $twins[$translation->ref->path] ?? null;
                    $patterns = $translation->sourcePatterns() ?: ($twin?->sourcePatterns() ?? []);

                    $affected[] = $match = $this->affected($translation, $patterns, $changed, $set, $query)
                        ?? $this->translationAffected($translation, $twin, $changed, $set);
                    $drift[] = $query->drift && $match === null ? $this->translationDrift($translation, $twin, $changed) : null;
                }
            }
        }

        return new AffectedReport(
            query: $query,
            changedFiles: $changed,
            pages: array_values(array_filter($affected)),
            drift: array_values(array_filter($drift)),
            sets: $sets,
            notes: $notes,
        );
    }

    /** @return list<CatalogSet> */
    private function sets(AffectedQuery $query): array
    {
        if ($query->set === null) {
            return $this->catalog->sets();
        }

        $set = $this->catalog->set($query->set);

        return $set === null ? [] : [$set];
    }

    /** @return list<string> */
    private function versions(CatalogSet $set, AffectedQuery $query, array &$notes): array
    {
        if ($query->version === 'all') {
            return $set->versions;
        }

        $version = $query->version ?? $set->defaultVersion;

        if (! in_array($version, $set->versions, true)) {
            $notes[] = "Set [{$set->key}] has no version [{$version}].";

            return [];
        }

        return [$version];
    }

    /**
     * @param  list<SourcePattern>  $patterns
     * @param  list<string>  $changed
     */
    private function affected(CatalogPage $page, array $patterns, array $changed, CatalogSet $set, AffectedQuery $query): ?AffectedPage
    {
        if ($patterns === []) {
            return null;
        }

        $matchedSources = [];
        $matchedFiles = [];

        foreach ($patterns as $pattern) {
            foreach ($changed as $file) {
                if ($this->isDocsFile($file, $set) || ! $this->globs->matches($pattern, $file)) {
                    continue;
                }

                if ($pattern->region !== null && ! $this->regionChanged($file, $pattern->region, $query)) {
                    continue;
                }

                $matchedSources[] = $pattern->raw;
                $matchedFiles[] = $file;
            }
        }

        if ($matchedSources === []) {
            return null;
        }

        return new AffectedPage(
            page: $page,
            status: $this->touched($page, $changed, $set) ? AffectedStatus::Updated : AffectedStatus::Stale,
            reason: AffectedReason::Sources,
            matchedSources: array_values(array_unique($matchedSources)),
            changedFiles: array_values(array_unique($matchedFiles)),
        );
    }

    /**
     * A translation is affected when its source page changed in this range
     * and the translation did not.
     *
     * @param  list<string>  $changed
     */
    private function translationAffected(CatalogPage $translation, ?CatalogPage $twin, array $changed, CatalogSet $set): ?AffectedPage
    {
        if ($twin === null || $twin->file === null || ! in_array($twin->file, $changed, true)) {
            return null;
        }

        return new AffectedPage(
            page: $translation,
            status: $this->touched($translation, $changed, $set) ? AffectedStatus::Updated : AffectedStatus::Stale,
            reason: AffectedReason::Translation,
            matchedSources: [$twin->file],
            changedFiles: [$twin->file],
        );
    }

    /**
     * Did this range update the page itself? For a repository set, the page's
     * file changed. For a database set, a docs migration that names the page
     * changed: that is how its update travels in the same push.
     *
     * @param  list<string>  $changed
     */
    private function touched(CatalogPage $page, array $changed, CatalogSet $set): bool
    {
        if ($page->file !== null) {
            return in_array($page->file, $changed, true);
        }

        $migrations = rtrim($set->migrationsPath, '/').'/';
        $path = preg_quote($page->ref->path, '/');

        foreach ($changed as $file) {
            if (! str_starts_with($file, $migrations) || ! str_ends_with($file, '.php')) {
                continue;
            }

            $contents = $this->workspace->read($file);

            if ($contents !== null && preg_match('/[\'"\/]'.$path.'[\'"]/', $contents)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $changed */
    private function drift(CatalogPage $page, array $changed): ?DriftedPage
    {
        $patterns = $page->sourcePatterns();

        if ($patterns === [] || ($page->file !== null && in_array($page->file, $changed, true))) {
            return null;
        }

        $since = $page->file !== null ? $this->vcs->lastCommit($page->file) : $page->updatedAt;

        if ($since === null) {
            return null;
        }

        $globs = array_values(array_unique(array_map(fn (SourcePattern $p) => $p->glob, $patterns)));
        $commit = $this->vcs->firstCommitTouching($globs, $since);

        if ($commit === null) {
            return null;
        }

        return new DriftedPage($page, $since instanceof Commit ? $since->date : $since, $commit);
    }

    /** @param list<string> $changed */
    private function translationDrift(CatalogPage $translation, ?CatalogPage $twin, array $changed): ?DriftedPage
    {
        if ($twin === null || $translation->file === null || $twin->file === null || in_array($translation->file, $changed, true)) {
            return null;
        }

        $mine = $this->vcs->lastCommit($translation->file);
        $source = $mine === null ? null : $this->vcs->firstCommitTouching([$twin->file], $mine);

        if ($mine === null || $source === null) {
            return null;
        }

        return new DriftedPage($translation, $mine->date, $source, AffectedReason::Translation);
    }

    /**
     * Only changes inside the region count. When the region or the changed
     * lines cannot be found, the whole file counts: a false "stale" costs a
     * look, a false "fine" costs a wrong page.
     */
    private function regionChanged(string $file, string $region, AffectedQuery $query): bool
    {
        $contents = $this->workspace->read($file);
        $range = $contents === null ? null : $this->regions->find($contents, $region);

        if ($range === null) {
            return true;
        }

        $lines = $this->vcs->changedLines($query->base, $query->head, $query->includesUncommitted(), $file);

        return $lines === null || $this->regions->overlaps($range, $lines);
    }

    /** Docs files never count as code changes for other docs pages. */
    private function isDocsFile(string $file, CatalogSet $set): bool
    {
        return $set->docsPath !== null && $set->docsPath !== '' && str_starts_with($file, rtrim($set->docsPath, '/').'/');
    }
}
