<?php

namespace LaRecipe\Domains\Markdown\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;

/**
 * Renders a page's Markdown twin for agents (`.md` URLs, llms-full.txt, MCP
 * read_page, Ask AI). Other domains call this instead of the renderer itself.
 */
final class RenderAgentMarkdownAction
{
    public function __construct(private readonly AgentMarkdownRenderer $renderer) {}

    /**
     * @param  array{url: string, base?: ?string, llms?: ?string, mcp?: ?string, skill?: ?string, version?: ?string, locale?: ?string}  $meta
     * @param  array<string, mixed>  $context
     */
    public function execute(Page $page, array $meta, ?LinkResolver $links = null, array $context = []): string
    {
        return $this->renderer->render($page, $meta, $links, $context);
    }
}
