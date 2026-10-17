<?php

namespace LaRecipe\Domains\Sync\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * One page and the code it documents.
 *
 * $file is the page's Markdown file relative to the project root for a
 * repository set, and null for a database set, whose pages are rows. A
 * database page's $updatedAt stands in for "the last commit to the file".
 */
final readonly class CatalogPage
{
    /** @param list<string> $sources the page's `sources:` entries, as written */
    public function __construct(
        public PageRef $ref,
        public string $title,
        public array $sources = [],
        public ?string $file = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?string $description = null,
        public bool $hidden = false,
        public bool $private = false,
        public ?string $body = null,
    ) {}

    /** @return list<SourcePattern> */
    public function sourcePatterns(): array
    {
        return array_values(array_map(SourcePattern::parse(...), array_filter($this->sources, fn (string $s) => trim($s) !== '')));
    }

    /** The page's name in messages: its file for a repository set, set:version/path otherwise. */
    public function label(): string
    {
        if ($this->file !== null) {
            return $this->file;
        }

        $version = $this->ref->version === 'current' ? '' : $this->ref->version.'/';

        return "{$this->ref->set}:{$version}{$this->ref->path}";
    }

    public function toArray(): array
    {
        return [
            'set' => $this->ref->set,
            'version' => $this->ref->version,
            'locale' => $this->ref->locale,
            'path' => $this->ref->path,
            'title' => $this->title,
            'file' => $this->file,
            'sources' => $this->sources,
        ];
    }
}
