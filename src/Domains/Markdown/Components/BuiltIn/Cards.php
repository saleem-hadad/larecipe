<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;
use LaRecipe\Domains\Markdown\Support\ContentIcons;

/**
 * Link blocks and layout: Card, CardGroup and Columns. A card with an href is a
 * real link, so it works without JavaScript and reads as a list item to agents.
 */
final class Cards
{
    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [
            new ComponentDefinition(
                name: 'Card',
                props: [
                    'title' => ['type' => 'string', 'description' => 'The card title (optional: a card can be body only, as v2 cards were)'],
                    'href' => ['type' => 'string', 'description' => 'Where the card links to (a relative .md link works)'],
                    'icon' => ['type' => 'string', 'description' => 'An icon by its Lucide name, from LaRecipe\'s content set: rocket, book-open, refresh-cw, globe, lock, shield-check, code, terminal, and more'],
                ],
                fallback: fn (array $props, string $children) => self::card($props, $children),
                markdown: function (array $props, string $children) {
                    $title = Markup::string($props, 'title');
                    $href = Markup::string($props, 'href');
                    $description = Markup::oneLine($children);

                    if ($title === '') {
                        return $href !== '' ? "- [{$description}]({$href})" : $children;
                    }

                    return '- '.($href !== '' ? "[{$title}]({$href})" : "**{$title}**").($description !== '' ? ': '.$description : '');
                },
                description: 'A link block with a title and a short description.',
            ),
            new ComponentDefinition(
                name: 'CardGroup',
                props: [
                    'cols' => ['type' => 'integer', 'default' => 2, 'description' => 'Columns on wide screens'],
                ],
                fallback: fn (array $props, string $children) => sprintf('<div class="lr-card-group" data-cols="%s">%s</div>', Markup::e($props['cols']), $children),
                markdown: fn (array $props, string $children) => (string) preg_replace("/\n\n(?=- )/", "\n", $children),
                description: 'A grid of cards.',
            ),
            new ComponentDefinition(
                name: 'Columns',
                props: [
                    'cols' => ['type' => 'integer', 'default' => 2, 'description' => 'Columns on wide screens'],
                ],
                fallback: fn (array $props, string $children) => sprintf('<div class="lr-columns" data-cols="%s">%s</div>', Markup::e($props['cols']), $children),
                markdown: fn (array $props, string $children) => $children,
                description: 'Side-by-side content.',
            ),
        ];
    }

    private static function card(array $props, string $children): string
    {
        $icon = Markup::string($props, 'icon');
        $href = Markup::string($props, 'href');
        $attributes = $icon !== '' ? ' data-icon="'.Markup::e($icon).'"' : '';
        $svg = $icon !== '' ? ContentIcons::svg($icon, 18) : null;
        $title = Markup::string($props, 'title');
        $inner = ($svg !== null ? '<span class="lr-card-icon">'.$svg.'</span>' : '')
            .($title !== '' ? '<div class="lr-card-title">'.Markup::e($title).'</div>' : '')
            .'<div class="lr-card-body">'.Markup::unwrapParagraph($children).'</div>';

        return $href !== ''
            ? '<a class="lr-card" href="'.Markup::e($href).'"'.$attributes.'>'.$inner.'</a>'
            : '<div class="lr-card"'.$attributes.'>'.$inner.'</div>';
    }
}
