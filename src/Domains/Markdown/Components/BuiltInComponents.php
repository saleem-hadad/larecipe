<?php

namespace LaRecipe\Domains\Markdown\Components;

use League\CommonMark\Util\Xml;

/**
 * The components every LaRecipe site has. Each one renders semantic HTML that
 * works without JavaScript and degrades to portable Markdown for agents.
 */
final class BuiltInComponents
{
    public const CALLOUT_TYPES = ['note', 'tip', 'important', 'warning', 'danger', 'caution', 'info', 'success'];

    public static function register(ComponentRegistry $registry): void
    {
        $registry->register(new ComponentDefinition(
            name: 'Callout',
            props: [
                'type' => ['type' => 'string', 'default' => 'note', 'enum' => self::CALLOUT_TYPES, 'description' => 'note, tip, important, warning, danger'],
                'title' => ['type' => 'string', 'description' => 'Optional label shown above the content'],
            ],
            fallback: fn (array $props, string $children) => sprintf(
                '<aside class="lr-callout" data-type="%s" role="note"><div class="lr-callout-label">%s</div><div class="lr-callout-body">%s</div></aside>',
                Xml::escape((string) $props['type']),
                Xml::escape((string) ($props['title'] ?? ucfirst((string) $props['type']))),
                $children,
            ),
            markdown: fn (array $props, string $children) => self::alert((string) $props['type'], $children, $props['title'] ?? null),
            description: 'A highlighted note, tip or warning.',
        ));
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
