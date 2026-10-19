<?php

namespace LaRecipe\Domains\Search\Actions;

use LaRecipe\Domains\Search\Services\SearchIndexRepository;

/**
 * Drops the cached search records of one version and locale, so the next
 * search rebuilds them (larecipe:clear).
 */
final class ForgetSearchIndexAction
{
    public function __construct(private readonly SearchIndexRepository $indexes) {}

    public function execute(string $set, string $version, string $locale): void
    {
        $this->indexes->forget($set, $version, $locale);
    }
}
