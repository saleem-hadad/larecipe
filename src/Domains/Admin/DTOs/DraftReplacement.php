<?php

namespace LaRecipe\Domains\Admin\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A reviewer's own version of a pending draft or conflict: for a page its
 * frontmatter and body, for the navigation a tree, for the manifest the whole
 * larecipe.json. Whatever is left out is taken from the draft as proposed.
 */
final readonly class DraftReplacement
{
    /**
     * @param  array<string, mixed>|null  $frontmatter
     * @param  array<string, mixed>|null  $tree
     * @param  array<string, mixed>|null  $manifest
     */
    public function __construct(
        public string $set,
        public Revision $draft,
        public Provenance $author,
        public ?array $frontmatter = null,
        public ?string $body = null,
        public ?array $tree = null,
        public ?array $manifest = null,
        public ?string $summary = null,
        public ?WriteMode $mode = null,
    ) {}
}
