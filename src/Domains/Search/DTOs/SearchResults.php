<?php

namespace LaRecipe\Domains\Search\DTOs;

/**
 * What a search driver returns: the hits for one query, best first.
 */
final readonly class SearchResults
{
    /** @param list<SearchHit> $hits */
    public function __construct(
        public array $hits = [],
        public ?int $total = null,
    ) {}
}
