<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A search ran: MCP search_docs, the /ask endpoint, Ask AI retrieval, the
 * server search endpoint, or the palette's client index (reported by the
 * browser in batches). The zero-result flag lets hosts find gaps in their docs.
 */
final class SearchPerformed
{
    public readonly bool $zeroResults;

    /**
     * @param  string  $source  "mcp", "endpoint", "assistant", "server" or "palette"
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
