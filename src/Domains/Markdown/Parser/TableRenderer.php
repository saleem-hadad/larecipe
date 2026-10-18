<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Extension\Table\TableRenderer as CommonMarkTableRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Wraps every Markdown table in <div class="lr-table">: the wrapper scrolls a
 * wide table sideways and carries the rounded border, so the table itself
 * can fill the article's width.
 */
final class TableRenderer implements NodeRendererInterface
{
    public function __construct(private readonly NodeRendererInterface $table = new CommonMarkTableRenderer) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        return new HtmlElement('div', ['class' => 'lr-table'], $this->table->render($node, $childRenderer));
    }
}
