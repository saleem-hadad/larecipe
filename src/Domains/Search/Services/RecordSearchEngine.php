<?php

namespace LaRecipe\Domains\Search\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Domains\Search\DTOs\SearchRecord;

/**
 * Server-side search over the same records as the browser index: MCP
 * search_docs, Ask AI retrieval, Markdown 404 suggestions and the server
 * endpoint all come through here.
 *
 * Only records the reader may see are scored: their page must be in the
 * query's visible pages and their gate abilities satisfied. A set with a
 * custom driver is searched through that driver instead.
 */
final class RecordSearchEngine implements SearchEngine
{
    /** A page contributes at most this many sections, so one long page cannot fill the results. */
    public const HITS_PER_PAGE = 2;

    private const SNIPPET_LENGTH = 180;

    public function __construct(
        private readonly SearchIndexRepository $indexes,
        private readonly Ranker $ranker,
        private readonly Tokenizer $tokenizer,
        private readonly SearchDrivers $drivers,
    ) {}

    public function search(SearchQuery $query): array
    {
        $driver = $this->drivers->for($query->set);

        if ($driver !== null) {
            $this->indexes->syncTo($driver, $query->set, $query->version, $query->locale);

            return array_slice($driver->search($query)->hits, 0, $query->limit);
        }

        $index = $this->indexes->get($query->set, $query->version, $query->locale);
        $allowed = [];

        foreach ($index->records as $position => $record) {
            if (isset($query->visible[$record->path]) && $record->allows($query->abilities)) {
                $allowed[$position] = true;
            }
        }

        $hits = [];
        $perPage = [];

        foreach ($this->ranker->rank($index, $query->query, $allowed) as [$position, $score]) {
            $record = $index->records[$position];

            if (($perPage[$record->path] ?? 0) >= self::HITS_PER_PAGE) {
                continue;
            }

            $perPage[$record->path] = ($perPage[$record->path] ?? 0) + 1;
            $hits[] = $this->hit($query, $record, $score);

            if (count($hits) >= $query->limit) {
                break;
            }
        }

        return $hits;
    }

    private function hit(SearchQuery $query, SearchRecord $record, float $score): SearchHit
    {
        return new SearchHit(
            ref: new PageRef($query->set, $query->version, $query->locale, $record->path),
            title: $record->title,
            heading: $record->heading,
            anchor: $record->anchor,
            snippet: $this->snippet($record->text, $query->query),
            score: $score,
            description: $query->visible[$record->path]->description ?? $record->description,
        );
    }

    /** A window of the text around the first query term it contains. */
    private function snippet(string $text, string $query): ?string
    {
        if ($text === '') {
            return null;
        }

        $start = 0;

        foreach ($this->tokenizer->query($query)['terms'] as $term) {
            $found = mb_stripos($text, $term);

            if ($found !== false) {
                $start = max(0, $found - 40);

                break;
            }
        }

        $snippet = mb_substr($text, $start, self::SNIPPET_LENGTH);

        return ($start > 0 ? '…' : '').trim($snippet).(mb_strlen($text) > $start + self::SNIPPET_LENGTH ? '…' : '');
    }
}
