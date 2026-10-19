<?php

namespace LaRecipe\Domains\Search\Contracts;

use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;

/**
 * Server-side search, used by MCP search_docs, Ask AI retrieval, the /ask
 * endpoint, Markdown 404 suggestions and the "server" search driver.
 */
interface SearchEngine
{
    /** @return list<SearchHit> best first */
    public function search(SearchQuery $query): array;
}
