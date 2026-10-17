<?php

namespace LaRecipe\Domains\Delivery\Support;

use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Components\RenderedComponent;

/**
 * Renders the components placed at an insert point (article.after, footer, ...)
 * as server fallbacks wrapped in island placeholders, so they work without
 * JavaScript and React mounts them in place without shifting the layout.
 */
final class InsertRenderer
{
    public function __construct(private readonly ComponentRegistry $components) {}

    /** @param list<string> $names */
    public function render(array $names, array $boot): string
    {
        $html = '';

        foreach ($names as $name) {
            $definition = $this->components->get($name);
            $props = $definition?->resolveProps([]) ?? [];
            $fallback = '';

            if ($definition?->fallback !== null) {
                $value = ($definition->fallback)($props, '', new RenderedComponent($name, $props, $boot['set']['key'] ?? null, $boot['version'] ?? null, $boot['locale']['code'] ?? null, $boot['page']['path'] ?? null));
                $fallback = is_object($value) && method_exists($value, 'render') ? (string) $value->render() : (string) $value;
            }

            $html .= sprintf(
                '<div data-lr-component="%s" data-lr-props=\'%s\'>%s</div>',
                e($name),
                json_encode($props, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}',
                $fallback,
            );
        }

        return $html;
    }
}
