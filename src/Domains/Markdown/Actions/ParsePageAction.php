<?php

namespace LaRecipe\Domains\Markdown\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\Services\PageRenderer;
use League\CommonMark\Node\Block\Document;

/**
 * Parses one page into the finished AST every projection reads: components
 * resolved, includes inlined, heading ids assigned. Search records and other
 * projections start here instead of re-parsing the source themselves.
 */
final class ParsePageAction
{
    public function __construct(private readonly PageRenderer $renderer) {}

    /** @param array<string, mixed> $context render context (purpose, abilities, snippets_root) */
    public function execute(Page $page, ?LinkResolver $links = null, array $context = []): Document
    {
        return $this->renderer->parse($page, $links, $context);
    }
}
