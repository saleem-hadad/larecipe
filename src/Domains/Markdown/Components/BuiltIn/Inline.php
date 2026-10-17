<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;

/**
 * Small labels and actions written on one line: `<Badge tone="success">Stable</Badge>`
 * and `<Button href="installation.md">Install</Button>`, plus FileTree, which
 * wraps an ordinary Markdown list.
 */
final class Inline
{
    public const TONES = ['neutral', 'info', 'success', 'warning', 'danger'];

    public const VARIANTS = ['primary', 'secondary', 'outline', 'ghost'];

    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [
            new ComponentDefinition(
                name: 'Badge',
                props: [
                    'tone' => ['type' => 'string', 'default' => 'info', 'enum' => self::TONES, 'description' => 'neutral, info, success, warning or danger'],
                ],
                fallback: fn (array $props, string $children) => sprintf(
                    '<p><span class="lr-badge" data-tone="%s">%s</span></p>',
                    Markup::e($props['tone']),
                    Markup::unwrapParagraph($children),
                ),
                markdown: fn (array $props, string $children) => '**'.Markup::oneLine($children).'**',
                description: 'A short status label, written on its own line.',
            ),
            new ComponentDefinition(
                name: 'Button',
                props: [
                    'href' => ['type' => 'string', 'required' => true, 'description' => 'Where the button links to'],
                    'variant' => ['type' => 'string', 'default' => 'primary', 'enum' => self::VARIANTS, 'description' => 'primary, secondary, outline or ghost'],
                ],
                fallback: fn (array $props, string $children) => sprintf(
                    '<a class="lr-button lr-button-%s" href="%s">%s</a>',
                    Markup::e($props['variant']),
                    Markup::e($props['href'] ?? ''),
                    Markup::unwrapParagraph($children),
                ),
                markdown: fn (array $props, string $children) => '['.Markup::oneLine($children).']('.Markup::string($props, 'href').')',
                description: 'A link styled as a button (kept for v2 compatibility).',
            ),
            new ComponentDefinition(
                name: 'FileTree',
                fallback: fn (array $props, string $children) => '<div class="lr-file-tree">'.$children.'</div>',
                markdown: fn (array $props, string $children) => $children,
                description: 'A folder structure, written as a nested Markdown list.',
            ),
        ];
    }
}
