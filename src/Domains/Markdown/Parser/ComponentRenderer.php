<?php

namespace LaRecipe\Domains\Markdown\Parser;

use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Components\RenderedComponent;
use LaRecipe\Domains\Markdown\Services\RenderState;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;

/**
 * Renders a component node to its server fallback HTML. Islands are wrapped in
 * a placeholder the React runtime mounts into; an unknown component renders as
 * escaped text, never as raw HTML.
 */
final class ComponentRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly RenderState $state,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        assert($node instanceof ComponentNode);

        $children = $childRenderer->renderNodes($node->children());
        $definition = $this->registry->get($node->name);

        if ($definition === null) {
            $this->state->componentIssue($node->name, "Unknown component <{$node->name}>.");

            return $this->escaped($node, $children);
        }

        $props = $definition->resolveProps($node->props);

        foreach ($definition->propErrors($props) as $error) {
            $this->state->componentIssue($node->name, $error);
        }

        $this->state->usedComponent($node->name);

        $context = new RenderedComponent($node->name, $props, $this->state->ref?->set, $this->state->ref?->version, $this->state->ref?->locale, $this->state->ref?->path);
        $html = $definition->fallback !== null
            ? $this->stringify(($definition->fallback)($props, $children, $context))
            : '<div class="lr-component">'.$children.'</div>';

        if (! $definition->island) {
            return $html;
        }

        $json = json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}';

        return sprintf('<div data-lr-component="%s" data-lr-props=\'%s\'>%s</div>', Xml::escape($node->name), $json, $html);
    }

    private function stringify(mixed $value): string
    {
        if (is_object($value) && method_exists($value, 'toHtml')) {
            return (string) $value->toHtml();
        }

        if (is_object($value) && method_exists($value, 'render')) {
            return (string) $value->render();
        }

        return (string) $value;
    }

    private function escaped(ComponentNode $node, string $children): string
    {
        $tag = Xml::escape('<'.$node->name.($node->selfClosing ? ' />' : '>'));

        return $node->selfClosing
            ? '<p>'.$tag.'</p>'
            : '<p>'.$tag.'</p>'.$children.'<p>'.Xml::escape('</'.$node->name.'>').'</p>';
    }
}
