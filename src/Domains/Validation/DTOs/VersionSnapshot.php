<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * One version and locale of a set: its pages, its navigation.json (raw, so the
 * schema can be checked) and the files that collide on one path.
 */
final readonly class VersionSnapshot
{
    /**
     * @param  string  $label  the URL segments between the prefix and a page path: "", "2.x", "ar/2.x"
     * @param  ?array  $navigation  the decoded navigation.json, null when navigation is derived
     * @param  bool  $sharedNavigation  a translation using its version's navigation.json
     * @param  array<string, PageSnapshot>  $pages  keyed by path
     * @param  list<array{path: string, files: list<string>}>  $duplicates
     */
    public function __construct(
        public string $version,
        public string $locale,
        public string $label,
        public bool $defaultLocale = true,
        public bool $private = false,
        public ?array $navigation = null,
        public ?string $navigationFile = null,
        public ?string $navigationError = null,
        public bool $sharedNavigation = false,
        public array $pages = [],
        public array $duplicates = [],
    ) {}

    /** How an issue names a page: "2.x/guides/webhooks", or "guides/webhooks" on an unversioned set. */
    public function pageId(string $path): string
    {
        return $this->label === '' ? $path : $this->label.'/'.$path;
    }
}
