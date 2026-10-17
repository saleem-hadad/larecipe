<?php

namespace LaRecipe\Domains\Search\Services;

use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;

/**
 * The simplest engine that works: matches query words against page titles and
 * descriptions. The record-based ranker replaces it.
 */
final class TitleSearchEngine implements SearchEngine
{
    public function search(SearchQuery $query): array
    {
        $terms = array_filter(preg_split('/\s+/u', mb_strtolower(trim($query->query))) ?: []);

        if ($terms === []) {
            return [];
        }

        $hits = [];

        foreach ($query->visible as $summary) {
            $haystack = mb_strtolower($summary->title.' '.$summary->description.' '.$summary->ref->path);
            $score = 0.0;

            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $score += str_contains(mb_strtolower($summary->title), $term) ? 2 : 1;
                }
            }

            if ($score > 0) {
                $hits[] = new SearchHit($summary->ref, $summary->title, score: $score, description: $summary->description);
            }
        }

        usort($hits, fn (SearchHit $a, SearchHit $b) => $b->score <=> $a->score);

        return array_slice($hits, 0, $query->limit);
    }
}
