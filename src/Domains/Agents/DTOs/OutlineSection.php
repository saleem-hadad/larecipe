<?php

namespace LaRecipe\Domains\Agents\DTOs;

/**
 * A heading in a version's outline and the pages under it, with nested groups
 * as child sections.
 *
 * Group sections carry a slug: when an index grows past its size limit, each
 * top-level group moves to its own path-scoped file at {version}/{slug}/llms.txt.
 * Sections without a slug (pages outside any group) always stay in the main file.
 */
final readonly class OutlineSection
{
    /**
     * @param  list<IndexEntry>  $entries
     * @param  list<OutlineSection>  $children
     */
    public function __construct(
        public string $heading,
        public ?string $slug,
        public array $entries,
        public array $children = [],
    ) {}

    /** @return list<IndexEntry> this section's pages, then its children's, in reading order */
    public function allEntries(): array
    {
        $entries = $this->entries;

        foreach ($this->children as $child) {
            array_push($entries, ...$child->allEntries());
        }

        return $entries;
    }

    public function child(string $slug): ?self
    {
        foreach ($this->children as $child) {
            if ($child->slug === $slug) {
                return $child;
            }
        }

        return null;
    }

    /** The same section with descriptions dropped, the last resort for an oversized index. */
    public function compact(): self
    {
        return new self(
            $this->heading,
            $this->slug,
            array_map(fn (IndexEntry $entry) => $entry->withoutDescription(), $this->entries),
            array_map(fn (OutlineSection $child) => $child->compact(), $this->children),
        );
    }
}
