<?php

namespace LaRecipe\Domains\Search\Actions;

use LaRecipe\Domains\Search\DTOs\SearchIndex;
use LaRecipe\Domains\Search\Services\SearchIndexRepository;

/**
 * The search records of one version and locale of a set, built and cached
 * when the content changed. larecipe:optimize calls it to compile them ahead
 * of the first search.
 */
final class LoadSearchIndexAction
{
    public function __construct(private readonly SearchIndexRepository $indexes) {}

    public function execute(string $set, string $version, string $locale): SearchIndex
    {
        return $this->indexes->get($set, $version, $locale);
    }
}
