<?php

namespace LaRecipe\Domains\Search\Contracts;

use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Domains\Search\DTOs\SearchRecord;
use LaRecipe\Domains\Search\DTOs\SearchResults;

/**
 * A custom search engine for a doc set (Meilisearch, Typesense, your own),
 * registered with LaRecipe::extendSearch() and selected with search.driver.
 *
 * LaRecipe pushes every record (private pages and gated blocks included) when
 * the set's content changes. Records carry the abilities they require, so a
 * driver must filter by the reader's: the query carries the pages the reader
 * may see and the abilities they hold (the Same-Rules Rule). LaRecipe checks
 * every hit again and drops what the reader may not see; set SearchHit's
 * $record to the matched record's id, or a hit in a section with gated
 * blocks loses its snippet for readers who lack an ability there.
 */
interface SearchDriver
{
    /** @param iterable<SearchRecord> $records every record of one version and locale */
    public function index(string $set, string $version, string $locale, iterable $records): void;

    public function search(SearchQuery $query): SearchResults;

    public function flush(string $set, ?string $version = null): void;
}
