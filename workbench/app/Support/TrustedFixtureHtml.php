<?php

namespace Workbench\App\Support;

use LaRecipe\Domains\Markdown\Services\RenderState;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Lets one fixture doc set write raw HTML, so the UI can be developed and
 * tested against the exact component HTML the Markdown renderer emits
 * (src/Domains/Markdown/Components/COMPONENTS.md), independent of that
 * renderer. Every other set keeps LaRecipe's default: raw HTML is escaped.
 * Development only; never register this in an app.
 */
final class TrustedFixtureHtml implements NodeRendererInterface
{
    public function __construct(
        private readonly RenderState $state,
        private readonly string $set,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if ($this->state->ref?->set !== $this->set) {
            return null;
        }

        return $node instanceof HtmlBlock || $node instanceof HtmlInline ? $node->getLiteral() : null;
    }
}
