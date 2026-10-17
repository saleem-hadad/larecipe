<?php

namespace LaRecipe\Domains\Search\Services;

/**
 * Turns text into search terms without losing code identifiers.
 *
 * Docs are full of `Route::middleware`, `$request->user()`, `orders:prune`,
 * `snake_case` and `hasMany`. Splitting them on punctuation alone made v2 miss
 * them (#262, #377); keeping them whole made "middleware" miss. So an
 * identifier yields both: its parts ("route", "middleware") for ordinary
 * queries, and the whole identifier ("route::middleware") as an extra term
 * that ranks exact matches first.
 */
final class Tokenizer
{
    /** Characters that may sit inside one identifier. */
    private const CHUNK = '/[\p{L}\p{N}_$@\\\\:.\/>#-]+/u';

    private const MAX_LENGTH = 64;

    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'can', 'do', 'does', 'for', 'from', 'how', 'i', 'in', 'is',
        'it', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'which', 'with', 'you', 'your',
    ];

    /** @return list<string> every term in the text, repeated as often as it occurs */
    public function tokenize(string $text): array
    {
        $tokens = [];

        foreach ($this->chunks($text) as $chunk) {
            [$parts, $whole] = $this->split($chunk);
            array_push($tokens, ...$parts);

            if ($whole !== null) {
                $tokens[] = $whole;
            }
        }

        return $tokens;
    }

    /**
     * The terms of a query: the parts every result should match, and whole
     * identifiers that earn exact matches a bonus. Stop words are dropped
     * unless the query has nothing else.
     *
     * @return array{terms: list<string>, wholes: list<string>}
     */
    public function query(string $query): array
    {
        $terms = [];
        $wholes = [];

        foreach ($this->chunks($query) as $chunk) {
            [$parts, $whole] = $this->split($chunk);
            array_push($terms, ...$parts);

            if ($whole !== null) {
                $wholes[] = $whole;
            }
        }

        $terms = array_values(array_unique($terms));
        $meaningful = array_values(array_diff($terms, self::STOP_WORDS));

        return ['terms' => $meaningful !== [] ? $meaningful : $terms, 'wholes' => array_values(array_unique($wholes))];
    }

    /**
     * The parts of identifiers in a text, space separated: what a browser
     * tokenizer that splits on spaces and punctuation would otherwise miss
     * ("has" and "many" in hasMany, "request" in $request).
     */
    public function expansions(string $text): string
    {
        $parts = [];

        foreach ($this->chunks($text) as $chunk) {
            [$split, $whole] = $this->split($chunk);

            if ($whole !== null) {
                array_push($parts, ...$split);
            }
        }

        return implode(' ', array_values(array_unique($parts)));
    }

    /** @return list<string> */
    private function chunks(string $text): array
    {
        preg_match_all(self::CHUNK, $text, $matches);

        return $matches[0];
    }

    /**
     * @return array{0: list<string>, 1: ?string} the lowercased parts, and the
     *                                             whole identifier when it has more than one part
     */
    private function split(string $chunk): array
    {
        $parts = [];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', $chunk, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            foreach (preg_split('/(?<=\p{Ll})(?=\p{Lu})|(?<=\p{Lu})(?=\p{Lu}\p{Ll})/u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                $part = mb_strtolower($part);

                if ((mb_strlen($part) > 1 || ctype_digit($part)) && mb_strlen($part) <= self::MAX_LENGTH) {
                    $parts[] = $part;
                }
            }
        }

        $whole = mb_strtolower((string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $chunk));

        return [$parts, count($parts) > 1 && mb_strlen($whole) <= self::MAX_LENGTH ? $whole : null];
    }
}
