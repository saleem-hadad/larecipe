<?php

namespace LaRecipe\Domains\Search\DTOs;

/**
 * Every record of one version and locale of a set, with the inverted index the
 * PHP ranker scores against. Built once per content fingerprint and cached.
 *
 * Records of private pages and gated blocks are included: the index lives on
 * the server, and every search filters by what the reader may see.
 */
final readonly class SearchIndex
{
    /** Indexed fields, in the order postings and lengths use. Boosts live in the Ranker. */
    public const FIELDS = ['title', 'heading', 'tags', 'path', 'text'];

    /**
     * @param  list<SearchRecord>  $records
     * @param  array<string, array<int, array<int, int>>>  $postings  term => record => field => frequency
     * @param  array<int, array<int, int>>  $lengths  record => field => token count
     * @param  array<int, float>  $averages  field => average token count
     * @param  list<string>  $abilities  every ability a record refers to (without the "!")
     */
    public function __construct(
        public string $fingerprint,
        public array $records = [],
        public array $postings = [],
        public array $lengths = [],
        public array $averages = [],
        public array $abilities = [],
    ) {}

    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'records' => array_map(fn (SearchRecord $record) => $record->toArray(), $this->records),
            'postings' => $this->postings,
            'lengths' => $this->lengths,
            'averages' => $this->averages,
            'abilities' => $this->abilities,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            fingerprint: (string) $data['fingerprint'],
            records: array_map(fn (array $record) => SearchRecord::fromArray($record), $data['records'] ?? []),
            postings: $data['postings'] ?? [],
            lengths: $data['lengths'] ?? [],
            averages: $data['averages'] ?? [],
            abilities: $data['abilities'] ?? [],
        );
    }
}
