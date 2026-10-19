<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;

/**
 * Tabbed content and tabbed code. Without JavaScript every panel is shown in
 * order under its own label, so nothing is hidden from readers, crawlers or
 * agents; the React island turns them into tabs.
 */
final class Tabs
{
    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [
            new ComponentDefinition(
                name: 'Tabs',
                props: [
                    'default' => ['type' => 'string', 'description' => 'Label of the tab shown first'],
                    'sync' => ['type' => 'string', 'description' => 'Tabs with the same sync key switch together across the page'],
                ],
                fallback: fn (array $props, string $children) => '<div class="lr-tabs">'.$children.'</div>',
                markdown: fn (array $props, string $children) => $children,
                island: true,
                description: 'Tabbed content. Put one <Tab label="..."> inside for each tab.',
            ),
            new ComponentDefinition(
                name: 'Tab',
                props: [
                    'label' => ['type' => 'string', 'required' => true, 'description' => 'The tab label'],
                ],
                fallback: fn (array $props, string $children) => sprintf(
                    '<section class="lr-tab" data-label="%1$s"><h4 class="lr-tab-label">%1$s</h4><div class="lr-tab-body">%2$s</div></section>',
                    Markup::e($props['label'] ?? ''),
                    $children,
                ),
                markdown: fn (array $props, string $children) => '**'.Markup::string($props, 'label').'**'.($children !== '' ? "\n\n".$children : ''),
                description: 'One tab inside <Tabs>.',
            ),
            new ComponentDefinition(
                name: 'CodeGroup',
                fallback: fn (array $props, string $children) => '<div class="lr-code-group">'.$children.'</div>',
                markdown: fn (array $props, string $children) => $children,
                island: true,
                description: 'Consecutive code blocks shown as tabs, labelled by each block\'s title or language.',
            ),
        ];
    }
}
