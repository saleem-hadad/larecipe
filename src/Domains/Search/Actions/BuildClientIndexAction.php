<?php

namespace LaRecipe\Domains\Search\Actions;

use Closure;
use LaRecipe\Domains\Search\DTOs\Audience;
use LaRecipe\Domains\Search\DTOs\ClientIndex;
use LaRecipe\Domains\Search\Services\ClientIndexBuilder;
use LaRecipe\Domains\Search\Services\SearchIndexRepository;

/**
 * The client search index for one audience: only the records that audience may
 * see, so a private page or a gated block never reaches a browser that should
 * not have it. Reports an issue when the index is over the size budget.
 */
final class BuildClientIndexAction
{
    public function __construct(
        private readonly SearchIndexRepository $indexes,
        private readonly ClientIndexBuilder $builder,
    ) {}

    /**
     * @param  Closure(string $path, ?string $anchor): string  $url  the reader URL of a page section
     */
    public function execute(string $set, string $version, string $locale, Audience $audience, Closure $url, int $budgetKb = ClientIndex::BUDGET_KB): ClientIndex
    {
        return $this->builder->build($this->indexes->get($set, $version, $locale), $audience, $url, $budgetKb);
    }
}
