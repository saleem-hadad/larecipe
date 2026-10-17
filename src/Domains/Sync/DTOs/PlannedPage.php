<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * One page a sync run will hand to the agent, and why: the sources that
 * matched and the changed files behind them, so the final summary can name
 * the code that triggered each change.
 */
final readonly class PlannedPage
{
    /**
     * @param  string  $reason  one line for people and for the agent's prompt
     * @param  list<string>  $sourceFiles  the changed files that made the page stale
     * @param  list<string>  $matchedSources  the page's `sources:` entries they matched
     */
    public function __construct(
        public CatalogPage $page,
        public string $reason,
        public array $sourceFiles = [],
        public array $matchedSources = [],
    ) {}

    public function path(): string
    {
        return $this->page->ref->path;
    }
}
