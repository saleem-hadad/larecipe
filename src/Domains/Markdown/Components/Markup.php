<?php

namespace LaRecipe\Domains\Markdown\Components;

use League\CommonMark\Util\Xml;

/**
 * The few string helpers the built-in components share, so every fallback
 * escapes the same way and every Markdown form indents the same way.
 */
final class Markup
{
    public static function e(mixed $value): string
    {
        return Xml::escape(is_scalar($value) ? (string) $value : '');
    }

    /**
     * Children that are a single paragraph lose the <p>, so they can sit inside
     * phrasing content such as a <span> or a link label.
     */
    public static function unwrapParagraph(string $html): string
    {
        $html = trim($html);

        if (preg_match('#^<p>(.*)</p>$#s', $html, $matches) && ! str_contains($matches[1], '<p>')) {
            return $matches[1];
        }

        return $html;
    }

    /** Indents every non-empty line, so nested Markdown stays inside its list item. */
    public static function indent(string $text, string $prefix): string
    {
        return implode("\n", array_map(
            fn (string $line) => $line === '' ? '' : $prefix.$line,
            explode("\n", $text),
        ));
    }

    /** Collapses Markdown to one line, for list items such as a card's description. */
    public static function oneLine(string $markdown): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $markdown));
    }

    /** "Title · 2:40", leaving out whatever is empty. */
    public static function caption(mixed ...$parts): string
    {
        $parts = array_filter(array_map(fn ($part) => is_scalar($part) ? trim((string) $part) : '', $parts), fn ($part) => $part !== '');

        return implode(' · ', $parts);
    }

    public static function string(array $props, string $key): string
    {
        return is_scalar($props[$key] ?? null) ? trim((string) $props[$key]) : '';
    }
}
