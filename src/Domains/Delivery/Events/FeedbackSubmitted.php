<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A reader or an agent said whether a page helped. The page widget and the MCP
 * submit_feedback tool both fire it. LaRecipe stores no feedback: hosts listen
 * and forward it to their own tools.
 */
final class FeedbackSubmitted
{
    /**
     * @param  string  $source  "reader" (the page widget or Ask AI) or "agent" (MCP)
     */
    public function __construct(
        public readonly string $set,
        public readonly PageRef $page,
        public readonly bool $helpful,
        public readonly ?string $comment,
        public readonly string $source,
        public readonly string $kind,
    ) {}
}
