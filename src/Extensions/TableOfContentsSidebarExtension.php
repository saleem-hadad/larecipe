<?php

namespace SaleemHadad\LaRecipe\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Renderer\Block\ListBlockRenderer;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\TableOfContents\Node\TableOfContents;
use League\CommonMark\Extension\TableOfContents\TableOfContentsRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class TableOfContentsSidebarExtension implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(TableOfContents::class, $this, 10);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        assert($node instanceof TableOfContents);

        $render = new TableOfContentsRenderer(new ListBlockRenderer());

        return new HtmlElement(
            'aside',
            ['class' => 'table-of-contents-sidebar-wrapper'],
            new HtmlElement(
                'aside',
                ['class' => 'table-of-contents-sidebar'],
                [
                    new HtmlElement('h2', [], 'On this page'),
                    $render->render($node, $childRenderer)
                ]
            )
        );
    }
}