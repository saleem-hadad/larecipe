<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * A whole doc set, read once for validation: its manifest as written, every
 * version and locale with its pages, and its hand-written skills.
 */
final readonly class DocsSnapshot
{
    /**
     * @param  ?array  $manifest  the decoded larecipe.json, null when the set has none
     * @param  list<VersionSnapshot>  $versions
     * @param  list<SkillSnapshot>  $skills
     */
    public function __construct(
        public string $set,
        public ?array $manifest,
        public array $versions,
        public ValidationSettings $settings,
        public array $skills = [],
        public ?string $manifestError = null,
        public string $manifestFile = 'larecipe.json',
    ) {}

    public function pageCount(): int
    {
        return array_sum(array_map(fn (VersionSnapshot $version) => count($version->pages), $this->versions));
    }

    public function version(string $version, string $locale): ?VersionSnapshot
    {
        foreach ($this->versions as $candidate) {
            if ($candidate->version === $version && $candidate->locale === $locale) {
                return $candidate;
            }
        }

        return null;
    }
}
