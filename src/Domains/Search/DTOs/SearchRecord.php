<?php

namespace LaRecipe\Domains\Search\DTOs;

/**
 * One searchable section of a page: the page intro, or the text under one H2
 * or H3. A `<Can>` or `<Cannot>` block is its own record, so gated text never
 * shares a record with ungated text.
 *
 * Abilities are requirements: "view-billing" needs the reader to have the
 * ability, "!view-billing" needs the reader not to have it (a <Cannot> block).
 */
final readonly class SearchRecord
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $title,
        public ?string $heading = null,
        public ?string $anchor = null,
        public int $level = 1,
        public string $text = '',
        public array $tags = [],
        public array $abilities = [],
        public ?string $description = null,
    ) {}

    /** @param list<string> $granted abilities the reader has */
    public function allows(array $granted): bool
    {
        foreach ($this->abilities as $ability) {
            $negated = str_starts_with($ability, '!');
            $has = in_array($negated ? substr($ability, 1) : $ability, $granted, true);

            if ($has === $negated) {
                return false;
            }
        }

        return true;
    }

    /** The page intro record (before the first H2) rather than a section. */
    public function isPage(): bool
    {
        return $this->heading === null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'title' => $this->title,
            'heading' => $this->heading,
            'anchor' => $this->anchor,
            'level' => $this->level,
            'text' => $this->text,
            'tags' => $this->tags,
            'abilities' => $this->abilities,
            'description' => $this->description,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            path: (string) $data['path'],
            title: (string) $data['title'],
            heading: $data['heading'] ?? null,
            anchor: $data['anchor'] ?? null,
            level: (int) ($data['level'] ?? 1),
            text: (string) ($data['text'] ?? ''),
            tags: $data['tags'] ?? [],
            abilities: $data['abilities'] ?? [],
            description: $data['description'] ?? null,
        );
    }
}
