<?php

namespace LaRecipe\Domains\Search\Services;

use LaRecipe\Domains\Search\DTOs\SearchIndex;

/**
 * Scores records for a query with BM25 over weighted fields (BM25F): a match
 * in the title counts more than one in a heading, then tags, the page path and
 * the text. Agents, Ask AI, Markdown 404s and the server driver all search
 * through it, over the same records the browser index holds.
 *
 * Each query term matches exactly, by prefix ("migr" finds "migrations"), or
 * with a typo when nothing else matches: one edit for terms of four to seven
 * letters, two from eight. Records that match more of the query rank higher,
 * and a whole identifier ("route::middleware") adds a bonus for exact matches.
 */
final class Ranker
{
    /** Per field, in SearchIndex::FIELDS order: title, heading, tags, path, text. */
    private const BOOSTS = [4.0, 3.0, 2.0, 1.5, 1.0];

    private const K1 = 1.2;

    private const B = 0.75;

    private const PREFIX_WEIGHT = 0.7;

    private const TYPO_WEIGHTS = [1 => 0.5, 2 => 0.35];

    public function __construct(private readonly Tokenizer $tokenizer) {}

    /**
     * @param  array<int, true>  $allowed  record positions the reader may see
     * @return list<array{0: int, 1: float}> record position and score, best first
     */
    public function rank(SearchIndex $index, string $query, array $allowed): array
    {
        ['terms' => $terms, 'wholes' => $wholes] = $this->tokenizer->query($query);

        if ($terms === [] || $allowed === []) {
            return [];
        }

        $scores = [];
        $matched = [];

        foreach ($terms as $term) {
            foreach ($this->bestPerRecord($index, $term, $allowed) as $record => $score) {
                $scores[$record] = ($scores[$record] ?? 0.0) + $score;
                $matched[$record] = ($matched[$record] ?? 0) + 1;
            }
        }

        foreach ($wholes as $whole) {
            foreach ($index->postings[$whole] ?? [] as $record => $fields) {
                if (isset($scores[$record])) {
                    $scores[$record] += 0.5 * $this->idf($index, $whole) * $this->bm25($index, $record, $fields);
                }
            }
        }

        $normalized = mb_strtolower(trim($query));
        $ranked = [];

        foreach ($scores as $record => $score) {
            $coverage = $matched[$record] / count($terms);
            $ranked[] = [$record, $score * $coverage * $this->bonus($index, $record, $normalized)];
        }

        usort($ranked, fn (array $a, array $b) => $b[1] <=> $a[1] ?: $a[0] <=> $b[0]);

        return $ranked;
    }

    /** @return array<int, float> the best score each record gets for one query term */
    private function bestPerRecord(SearchIndex $index, string $term, array $allowed): array
    {
        $best = [];

        foreach ($this->candidates($index, $term) as $candidate => $weight) {
            $idf = $this->idf($index, $candidate);

            foreach ($index->postings[$candidate] as $record => $fields) {
                if (isset($allowed[$record])) {
                    $best[$record] = max($best[$record] ?? 0.0, $weight * $idf * $this->bm25($index, $record, $fields));
                }
            }
        }

        return $best;
    }

    /** @return array<string, float> index terms that match a query term, with how much each match counts */
    private function candidates(SearchIndex $index, string $term): array
    {
        $candidates = isset($index->postings[$term]) ? [$term => 1.0] : [];
        $length = mb_strlen($term);

        if ($length >= 2) {
            foreach (array_keys($index->postings) as $candidate) {
                $candidate = (string) $candidate;

                if ($candidate !== $term && str_starts_with($candidate, $term)) {
                    $candidates[$candidate] = self::PREFIX_WEIGHT;
                }
            }
        }

        if ($candidates !== [] || $length < 4 || strlen($term) !== $length) {
            return $candidates;
        }

        $allowedEdits = $length >= 8 ? 2 : 1;

        foreach (array_keys($index->postings) as $candidate) {
            $candidate = (string) $candidate;

            if (abs(strlen($candidate) - $length) <= $allowedEdits && ($distance = levenshtein($term, $candidate)) <= $allowedEdits) {
                $candidates[$candidate] = self::TYPO_WEIGHTS[$distance];
            }
        }

        return $candidates;
    }

    private function idf(SearchIndex $index, string $term): float
    {
        $total = max(1, count($index->records));
        $containing = count($index->postings[$term] ?? []);

        return log(1 + ($total - $containing + 0.5) / ($containing + 0.5));
    }

    /** @param array<int, int> $fields field position => term frequency */
    private function bm25(SearchIndex $index, int $record, array $fields): float
    {
        $frequency = 0.0;

        foreach ($fields as $field => $count) {
            $average = $index->averages[$field] ?? 0.0;
            $length = $index->lengths[$record][$field] ?? 0;
            $norm = $average > 0 ? (1 - self::B + self::B * $length / $average) : 1.0;
            $frequency += self::BOOSTS[$field] * $count / $norm;
        }

        return $frequency * (self::K1 + 1) / ($frequency + self::K1);
    }

    /** A query that is the title or heading itself wins; a page's intro edges out its sections. */
    private function bonus(SearchIndex $index, int $record, string $query): float
    {
        $entry = $index->records[$record];
        $bonus = $entry->isPage() ? 1.1 : 1.0;

        if (mb_strtolower($entry->title) === $query) {
            $bonus *= 1.5;
        } elseif ($entry->heading !== null && mb_strtolower($entry->heading) === $query) {
            $bonus *= 1.3;
        }

        return $bonus;
    }
}
