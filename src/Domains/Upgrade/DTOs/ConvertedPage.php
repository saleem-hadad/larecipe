<?php

namespace LaRecipe\Domains\Upgrade\DTOs;

/**
 * One v2 page after the content rewrites: its v3 path inside the version, the
 * frontmatter the upgrade seeded, and the new Markdown body.
 */
final readonly class ConvertedPage
{
    /**
     * @param  string  $version  the version folder ("2.2")
     * @param  string  $path  the page path inside the version, without extension ("overview", "guides/deploy")
     * @param  string  $file  the page file inside the version ("overview.md")
     * @param  array<string, mixed>  $frontmatter
     */
    public function __construct(
        public string $version,
        public string $path,
        public string $file,
        public array $frontmatter,
        public string $body,
        public bool $changed,
    ) {}
}
