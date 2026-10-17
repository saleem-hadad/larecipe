<?php

namespace LaRecipe\Domains\Search\DTOs;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * One search result: a page, optionally a section of it, and why it matched.
 */
final readonly class SearchHit
{
    public function __construct(
        public PageRef $ref,
        public string $title,
        public ?string $heading = null,
        public ?string $anchor = null,
        public ?string $snippet = null,
        public float $score = 0.0,
        public ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->ref->path,
            'title' => $this->title,
            'heading' => $this->heading,
            'anchor' => $this->anchor,
            'snippet' => $this->snippet,
            'score' => round($this->score, 4),
            'description' => $this->description,
        ];
    }
}
