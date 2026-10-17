<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * A `sources:` entry LaRecipe proposes for a page, and why: the inline code
 * in the page that names something defined in that file.
 */
final readonly class SourceSuggestion
{
    /** @param list<string> $evidence the inline code that led here */
    public function __construct(
        public CatalogPage $page,
        public string $source,
        public string $kind,
        public array $evidence,
    ) {}

    public function toArray(): array
    {
        return [
            'page' => $this->page->ref->path,
            'version' => $this->page->ref->version,
            'file' => $this->page->file,
            'source' => $this->source,
            'kind' => $this->kind,
            'evidence' => $this->evidence,
        ];
    }
}
