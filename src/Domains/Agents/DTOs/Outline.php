<?php

namespace LaRecipe\Domains\Agents\DTOs;

/**
 * One version and locale of a doc set as agents see it: the navigation's
 * sections in reading order, plus visible pages the navigation does not list.
 *
 * llms.txt, llms-full.txt, skill.md and list_pages are all built from it, so
 * every agent surface agrees with the sidebar.
 */
final readonly class Outline
{
    /**
     * @param  list<OutlineSection>  $sections
     * @param  list<IndexEntry>  $optional  visible, unhidden pages that are not in the navigation
     */
    public function __construct(
        public array $sections,
        public array $optional = [],
    ) {}

    /** @return list<IndexEntry> every page in reading order, optional pages last */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->sections as $section) {
            array_push($entries, ...$section->allEntries());
        }

        return [...$entries, ...$this->optional];
    }

    /** The section a split file covers, by its slug path ("billing" or "billing/webhooks"). */
    public function section(string $slugPath): ?OutlineSection
    {
        $slugs = explode('/', trim($slugPath, '/'));
        $first = array_shift($slugs);
        $found = null;

        foreach ($this->sections as $section) {
            if ($section->slug !== null && $section->slug === $first) {
                $found = $section;
            }
        }

        foreach ($slugs as $slug) {
            $found = $found?->child($slug);
        }

        return $found;
    }

    public function isEmpty(): bool
    {
        return $this->entries() === [];
    }
}
