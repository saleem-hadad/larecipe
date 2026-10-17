<?php

namespace LaRecipe\Domains\Sync\DTOs;

use LaRecipe\Domains\Sync\Enums\SyncMode;
use LaRecipe\Domains\Sync\Enums\SyncTarget;

/**
 * Everything a sync run decided before spending a token: the set, the pages
 * the agent will work on, where its changes land, and the change it reads.
 * The confirmation prompt shows exactly this.
 */
final readonly class SyncPlan
{
    /**
     * @param  list<PlannedPage>  $pages  the pages the agent works on
     * @param  list<PlannedPage>  $skipped  pages left out by the page budget
     * @param  ?string  $base  the diff base the run compares with; null means uncommitted changes only
     * @param  list<string>  $changedFiles  every file the change touched
     * @param  list<string>  $notes  things the person should know before saying yes
     */
    public function __construct(
        public SyncRequest $request,
        public CatalogSet $set,
        public SyncMode $mode,
        public SyncTarget $target,
        public string $version,
        public string $locale,
        public array $pages = [],
        public array $skipped = [],
        public ?string $base = null,
        public array $changedFiles = [],
        public array $notes = [],
    ) {}

    /** False when there is nothing for the agent to do, so no token is spent. */
    public function hasWork(): bool
    {
        return $this->pages !== [];
    }

    /** "origin/main...HEAD plus uncommitted changes", for people and for the prompt. */
    public function range(): string
    {
        return $this->base === null
            ? 'uncommitted changes'
            : "{$this->base}...{$this->request->head} plus uncommitted changes";
    }

    /** The planned page with this path, to tie a write back to the files that triggered it. */
    public function page(string $path): ?PlannedPage
    {
        foreach ($this->pages as $page) {
            if ($page->path() === $path) {
                return $page;
            }
        }

        return null;
    }
}
