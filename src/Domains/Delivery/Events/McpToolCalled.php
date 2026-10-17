<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * An MCP tool was called on a set's web endpoint or on the local server. MCP
 * traffic is always agent traffic, so the kind is "agent".
 */
final class McpToolCalled
{
    /**
     * @param  string  $transport  "web" or "local"
     * @param  array<string, scalar|null>  $arguments  the scalar arguments, long values shortened
     */
    public function __construct(
        public readonly string $set,
        public readonly string $tool,
        public readonly string $transport,
        public readonly ?PageRef $page = null,
        public readonly array $arguments = [],
        public readonly bool $successful = true,
        public readonly string $kind = 'agent',
    ) {}
}
