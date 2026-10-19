<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

/**
 * The share of an HTML article's distinct words (four letters or more) that
 * also appear in its Markdown twin. Every tag counts as a word break, so two
 * adjacent elements (a card's title and its text, a step number and its
 * label) never fuse into one word that no Markdown could contain.
 */
final class WordParity
{
    public static function measure(string $html, string $markdown): float
    {
        $article = preg_match('/<article[^>]*data-lr-article[^>]*>(.*?)<\/article>/s', $html, $matches) ? $matches[1] : $html;
        $text = (string) preg_replace('/<[^>]*>/', ' ', (string) preg_replace('/<(script|style)\b.*?<\/\1>/s', ' ', $article));
        $articleWords = self::words(html_entity_decode($text, ENT_QUOTES));
        $markdownWords = array_flip(self::words($markdown));

        if ($articleWords === []) {
            return 1.0;
        }

        $found = array_filter($articleWords, fn (string $word) => isset($markdownWords[$word]));

        return count($found) / count($articleWords);
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{4,}/u', mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0]));
    }
}
