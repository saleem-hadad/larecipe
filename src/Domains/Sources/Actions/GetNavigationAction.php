<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\Services\ZeroConfigNavigation;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * The navigation of one version and locale: navigation.json when the source has
 * one, otherwise derived from folders and frontmatter.
 */
final class GetNavigationAction
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly ZeroConfigNavigation $zeroConfig,
    ) {}

    /** @param array<string, PageSummary> $summaries the version's pages, from ListPageSummariesAction */
    public function execute(string $set, string $version, string $locale, array $summaries): NavigationTree
    {
        return $this->sources->for($set)->navigation($version, $locale)
            ?? $this->zeroConfig->build($summaries);
    }
}
