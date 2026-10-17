<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A server-side search ran (MCP search_docs, the /ask endpoint, Ask AI
 * retrieval). The zero-result flag lets hosts find gaps in their docs.
 */
final class SearchPerformed
{
    public readonly bool $zeroResults;

    /**
     * @param  string  $source  "mcp", "endpoint" or "assistant"
     */
    public function __construct(
        public readonly string $set,
        public readonly string $version,
        public readonly string $locale,
        public readonly string $query,
        public readonly int $results,
        public readonly string $source,
        public readonly string $kind,
        public readonly ?PageRef $page = null,
    ) {
        $this->zeroResults = $results === 0;
    }
}
