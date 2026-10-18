<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A reader or an agent said whether a page, or an Ask AI answer, helped. The
 * page widget, the thumbs on an Ask answer and the MCP submit_feedback tool all
 * fire it. LaRecipe stores no feedback: hosts listen and forward it to their
 * own tools.
 */
final class FeedbackSubmitted
{
    /**
     * @param  string  $source  "reader" (the page widget), "ask" (thumbs on an Ask AI answer) or "agent" (MCP)
     * @param  ?string  $question  for "ask": the question the answer was for; $page is where the reader asked it
     */
    public function __construct(
        public readonly string $set,
        public readonly PageRef $page,
        public readonly bool $helpful,
        public readonly ?string $comment,
        public readonly string $source,
        public readonly string $kind,
        public readonly ?string $question = null,
    ) {}
}
