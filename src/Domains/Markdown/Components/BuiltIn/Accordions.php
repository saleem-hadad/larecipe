<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;

/**
 * Collapsible sections. They are native <details> elements, so they open and
 * close without JavaScript and need no island.
 */
final class Accordions
{
    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [
            new ComponentDefinition(
                name: 'Accordion',
                props: [
                    'title' => ['type' => 'string', 'required' => true, 'description' => 'The always-visible summary'],
                    'open' => ['type' => 'boolean', 'default' => false, 'description' => 'Start expanded'],
                ],
                fallback: fn (array $props, string $children) => sprintf(
                    '<details class="lr-accordion"%s><summary>%s</summary><div class="lr-accordion-body">%s</div></details>',
                    $props['open'] === true ? ' open' : '',
                    Markup::e($props['title'] ?? ''),
                    $children,
                ),
                markdown: fn (array $props, string $children) => '**'.Markup::string($props, 'title').'**'.($children !== '' ? "\n\n".$children : ''),
                description: 'A collapsible section.',
            ),
            new ComponentDefinition(
                name: 'AccordionGroup',
                fallback: fn (array $props, string $children) => '<div class="lr-accordion-group">'.$children.'</div>',
                markdown: fn (array $props, string $children) => $children,
                description: 'A stack of accordions.',
            ),
        ];
    }
}
