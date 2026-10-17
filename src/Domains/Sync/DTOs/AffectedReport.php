<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * Everything `larecipe:affected` knows about one change: the files it
 * touched, the pages those files are documented in, and the pages that had
 * already drifted before it.
 */
final readonly class AffectedReport
{
    /**
     * @param  list<string>  $changedFiles
     * @param  list<AffectedPage>  $pages
     * @param  list<DriftedPage>  $drift
     * @param  list<CatalogSet>  $sets
     * @param  list<string>  $notes  things the reader should know (no git, a database set without sources)
     */
    public function __construct(
        public AffectedQuery $query,
        public array $changedFiles = [],
        public array $pages = [],
        public array $drift = [],
        public array $sets = [],
        public array $notes = [],
    ) {}

    /** @return list<AffectedPage> pages the change made stale */
    public function stale(): array
    {
        return array_values(array_filter($this->pages, fn (AffectedPage $page) => $page->isStale()));
    }

    /** @return list<AffectedPage> pages the change already updated */
    public function updated(): array
    {
        return array_values(array_filter($this->pages, fn (AffectedPage $page) => ! $page->isStale()));
    }

    public function hasStale(): bool
    {
        return $this->stale() !== [];
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query->toArray(),
            'summary' => [
                'changed_files' => count($this->changedFiles),
                'affected' => count($this->pages),
                'stale' => count($this->stale()),
                'updated' => count($this->updated()),
                'drift' => count($this->drift),
            ],
            'changed_files' => $this->changedFiles,
            'pages' => array_map(fn (AffectedPage $page) => $page->toArray(), $this->pages),
            'drift' => array_map(fn (DriftedPage $page) => $page->toArray(), $this->drift),
            'notes' => $this->notes,
        ];
    }
}
