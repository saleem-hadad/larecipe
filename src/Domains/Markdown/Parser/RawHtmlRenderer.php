<?php

namespace LaRecipe\Domains\Markdown\Parser;

use LaRecipe\Domains\Markdown\Services\RenderState;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlFilter;

/**
 * Raw HTML blocks and inline tags: escaped, unless the page's doc set allows
 * raw HTML (RenderState::$rawHtml, set per page from the set's html_input).
 * One environment serves every set, so the choice is made per render.
 */
final class RawHtmlRenderer implements NodeRendererInterface
{
    public function __construct(private readonly RenderState $state) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        $literal = $node instanceof HtmlBlock || $node instanceof HtmlInline ? $node->getLiteral() : '';

        return HtmlFilter::filter($literal, $this->state->rawHtml ? HtmlFilter::ALLOW : HtmlFilter::ESCAPE);
    }
}
