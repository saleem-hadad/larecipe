<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\DTOs\Asset;

/**
 * Where one doc set's content lives. A source instance is bound to one set.
 *
 * Every driver reads the same format (Markdown with YAML frontmatter,
 * larecipe.json, navigation.json), which is what makes moving between drivers lossless.
 */
interface ContentSource
{
    public function manifest(): ?Manifest;

    /** @return iterable<PageRef> */
    public function pages(string $version, string $locale): iterable;

    public function page(PageRef $ref): ?Page;

    /** null means "derive the navigation" (zero-config). */
    public function navigation(string $version, string $locale): ?NavigationTree;

    public function asset(string $version, string $path): ?Asset;

    /** Changes whenever any content in the set (or in one version) changes. */
    public function fingerprint(?string $version = null): string;
}
