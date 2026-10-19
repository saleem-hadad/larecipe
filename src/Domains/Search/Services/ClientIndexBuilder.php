<?php

namespace LaRecipe\Domains\Search\Services;

use Closure;
use LaRecipe\Domains\Search\DTOs\Audience;
use LaRecipe\Domains\Search\DTOs\ClientIndex;
use LaRecipe\Domains\Search\DTOs\SearchIndex;
use LaRecipe\Domains\Search\DTOs\SearchRecord;

/**
 * Writes the client index for one audience: the records it may see plus the
 * MiniSearch options, as JSON the browser loads on the first palette open.
 *
 * The browser builds the MiniSearch index from these records (a few
 * milliseconds for a typical set), which keeps the payload simple and smaller
 * than a serialized index. Identifier parts the browser's tokenizer would miss
 * ("has" and "many" in hasMany) are added as a low-weight "terms" field, so
 * client and server match the same words.
 */
final class ClientIndexBuilder
{
    public const FIELDS = ['title', 'heading', 'tags', 'text', 'terms'];

    public const STORE_FIELDS = ['title', 'heading', 'path', 'url', 'kind'];

    public const SEARCH_OPTIONS = [
        'boost' => ['title' => 4, 'heading' => 3, 'tags' => 2, 'terms' => 1.5, 'text' => 1],
        'prefix' => true,
        'fuzzy' => 0.2,
        'combineWith' => 'AND',
    ];

    public function __construct(private readonly Tokenizer $tokenizer) {}

    /**
     * @param  Closure(string $path, ?string $anchor): string  $url  the reader URL of a record
     */
    public function build(SearchIndex $index, Audience $audience, Closure $url, int $budgetKb): ClientIndex
    {
        $visible = array_flip($audience->paths);
        $records = array_values(array_filter(
            $index->records,
            fn (SearchRecord $record) => isset($visible[$record->path]) && $record->allows($audience->abilities),
        ));

        $json = (string) json_encode([
            'fingerprint' => $audience->fingerprint,
            'audience' => $audience->key,
            'fields' => self::FIELDS,
            'storeFields' => self::STORE_FIELDS,
            'searchOptions' => self::SEARCH_OPTIONS,
            'records' => array_map(fn (SearchRecord $record) => $this->record($record, $url), $records),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $gzip = strlen((string) gzencode($json, 6));
        $issues = [];

        if ($gzip > $budgetKb * 1024) {
            $issues[] = [
                'rule' => 'search.budget',
                'level' => 'warning',
                'message' => sprintf(
                    'The search index is %d KB gzipped, over the %d KB budget. Split the set into versions or locales, or switch it to the server search driver.',
                    (int) ceil($gzip / 1024),
                    $budgetKb,
                ),
            ];
        }

        return new ClientIndex($audience, $json, count($records), strlen($json), $gzip, $issues);
    }

    private function record(SearchRecord $record, Closure $url): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'heading' => $record->heading,
            'tags' => implode(' ', $record->tags),
            'text' => $record->text,
            'terms' => trim($this->tokenizer->expansions($record->title.' '.$record->heading.' '.$record->text).' '.str_replace(['/', '-', '_'], ' ', $record->path)),
            'path' => $record->path,
            'url' => $url($record->path, $record->anchor),
            'kind' => $record->isPage() ? 'page' : 'section',
        ];
    }
}
