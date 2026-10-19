<?php

namespace LaRecipe\Domains\Agents\DTOs;

/**
 * One line of an agent index: what llms.txt prints as "- [Title](url): description".
 */
final readonly class IndexEntry
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $description = null,
        public ?string $path = null,
    ) {}

    public function withoutDescription(): self
    {
        return new self($this->title, $this->url, null, $this->path);
    }

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'path' => $this->path,
            'url' => $this->url,
            'description' => $this->description,
        ], fn ($value) => $value !== null);
    }
}
