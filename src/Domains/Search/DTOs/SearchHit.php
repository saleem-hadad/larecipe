<?php

namespace LaRecipe\Domains\Search\DTOs;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * One search result: a page, optionally a section of it, and why it matched.
 *
 * $record is the id of the SearchRecord that matched. A custom driver should
 * set it: a section can hold gated blocks that share its anchor, and only the
 * record says which text the snippet quotes.
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
        public ?string $record = null,
    ) {}

    public function withoutSnippet(): self
    {
        return new self($this->ref, $this->title, $this->heading, $this->anchor, null, $this->score, $this->description, $this->record);
    }

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
