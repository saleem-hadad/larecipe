<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A page's agent Markdown twin was served (a .md URL or Accept: text/markdown).
 *
 * Fired next to PageViewed, so hosts that only care about agent reads can
 * listen to this one. LaRecipe stores nothing.
 */
final class MarkdownServed
{
    public function __construct(
        public readonly string $set,
        public readonly PageRef $page,
        public readonly string $kind,
        public readonly ?string $userAgent = null,
        public readonly bool $explicit = false,
    ) {}
}
