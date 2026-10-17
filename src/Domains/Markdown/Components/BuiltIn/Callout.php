<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;

/**
 * A highlighted note, tip or warning. GitHub alerts (`> [!WARNING]`) and
 * `:::warning` directives become the same component, so all three spellings
 * render identically.
 */
final class Callout
{
    public const TYPES = ['note', 'tip', 'important', 'warning', 'danger', 'caution', 'info', 'success'];

    /** GitHub alert markers and the callout type each one becomes. */
    public const ALERTS = ['NOTE' => 'note', 'TIP' => 'tip', 'IMPORTANT' => 'important', 'WARNING' => 'warning', 'CAUTION' => 'caution'];

    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [new ComponentDefinition(
            name: 'Callout',
            props: [
                'type' => ['type' => 'string', 'default' => 'note', 'enum' => self::TYPES, 'description' => 'note, tip, important, warning, caution or danger'],
                'title' => ['type' => 'string', 'description' => 'Optional label shown above the content'],
            ],
            fallback: fn (array $props, string $children) => sprintf(
                '<aside class="lr-callout" data-type="%s" role="note"><div class="lr-callout-label">%s</div><div class="lr-callout-body">%s</div></aside>',
                Markup::e($props['type']),
                Markup::e($props['title'] ?? ucfirst((string) $props['type'])),
                $children,
            ),
            markdown: fn (array $props, string $children) => self::alert((string) $props['type'], $children, $props['title'] ?? null),
            description: 'A highlighted note, tip or warning.',
        )];
    }

    /** A GitHub alert: portable on GitHub and readable by every agent. */
    public static function alert(string $type, string $children, ?string $title = null): string
    {
        $kind = match ($type) {
            'tip', 'success' => 'TIP',
            'important' => 'IMPORTANT',
            'warning' => 'WARNING',
            'danger', 'caution' => 'CAUTION',
            default => 'NOTE',
        };

        $lines = ["> [!{$kind}]"];

        if ($title !== null && $title !== '') {
            $lines[] = "> **{$title}**";
        }

        foreach (explode("\n", rtrim($children)) as $line) {
            $lines[] = rtrim('> '.$line);
        }

        return implode("\n", $lines);
    }
}
