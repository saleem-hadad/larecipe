<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * LaRecipe rendered a page (ARCHITECTURE.md 9.7, an extension point).
 *
 * When it fires:
 * - "html": the article was rendered because the render cache did not have it
 *   (a changed page, a cold cache, or larecipe:optimize compiling it). A request
 *   answered from the render cache does not fire it.
 * - "markdown": a page's agent Markdown twin was rendered for a .md request or
 *   Accept: text/markdown. Agent Markdown is not cached, so this fires on
 *   every such request.
 *
 * It carries the page and the format only, never anything about the reader:
 * readers are counted with PageViewed and MarkdownServed.
 */
final class PageRendered
{
    public const HTML = 'html';

    public const MARKDOWN = 'markdown';

    public function __construct(
        public readonly string $set,
        public readonly PageRef $page,
        public readonly string $format,
        public readonly string $contentHash,
    ) {}
}
