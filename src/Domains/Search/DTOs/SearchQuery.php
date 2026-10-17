<?php

namespace LaRecipe\Domains\Search\DTOs;

use LaRecipe\Domains\Content\DTOs\PageSummary;

/**
 * A search over one version and locale of a set, limited to what the reader may see.
 */
final readonly class SearchQuery
{
    /**
     * @param  array<string, PageSummary>  $visible  pages the reader may see, keyed by path
     * @param  list<string>  $abilities  gate abilities the reader has (for <Can> sections)
     */
    public function __construct(
        public string $set,
        public string $version,
        public string $locale,
        public string $query,
        public array $visible,
        public int $limit = 10,
        public array $abilities = [],
    ) {}
}
