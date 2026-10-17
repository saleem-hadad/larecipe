<?php

namespace LaRecipe\Domains\Search\Services;

use LaRecipe\Domains\Search\DTOs\SearchIndex;
use LaRecipe\Domains\Search\DTOs\SearchRecord;

/**
 * Builds the inverted index the PHP ranker scores against: for every term,
 * which records contain it and how often, per field.
 */
final class SearchIndexer
{
    public function __construct(private readonly Tokenizer $tokenizer) {}

    /** @param list<SearchRecord> $records */
    public function build(string $fingerprint, array $records): SearchIndex
    {
        $postings = [];
        $lengths = [];
        $totals = array_fill(0, count(SearchIndex::FIELDS), 0);
        $abilities = [];

        foreach ($records as $index => $record) {
            foreach ($this->fields($record) as $field => $value) {
                $tokens = $this->tokenizer->tokenize($value);
                $lengths[$index][$field] = count($tokens);
                $totals[$field] += count($tokens);

                foreach (array_count_values($tokens) as $term => $frequency) {
                    $postings[(string) $term][$index][$field] = $frequency;
                }
            }

            foreach ($record->abilities as $ability) {
                $abilities[ltrim($ability, '!')] = true;
            }
        }

        $averages = array_map(fn (int $total) => $records === [] ? 0.0 : $total / count($records), $totals);

        return new SearchIndex($fingerprint, array_values($records), $postings, $lengths, $averages, array_keys($abilities));
    }

    /** @return array<int, string> field position => text, in SearchIndex::FIELDS order */
    private function fields(SearchRecord $record): array
    {
        return [
            $record->title,
            (string) $record->heading,
            implode(' ', $record->tags),
            str_replace(['/', '-', '_'], ' ', $record->path === 'index' ? '' : $record->path),
            $record->text,
        ];
    }
}
