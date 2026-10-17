<?php

namespace LaRecipe\Domains\Markdown\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use LaRecipe\Domains\Markdown\Services\PageRenderer;

/**
 * Renders one page's article HTML, its table of contents and what validation needs.
 */
final class RenderPageAction
{
    public function __construct(private readonly PageRenderer $renderer) {}

    /** @param array<string, mixed> $context extras for extensions (the reader's abilities, flags) */
    public function execute(Page $page, ?LinkResolver $links = null, array $context = []): RenderedPage
    {
        return $this->renderer->render($page, $links, $context);
    }
}
