<?php

namespace LaRecipe\Domains\Validation\Contracts;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;

/**
 * Reads docs for validation. The Laravel adapter reads the set's source and
 * renders every page with the real renderer, so validation sees exactly what
 * readers and agents get.
 */
interface DocsInspector
{
    /** @return list<string> every configured set key */
    public function sets(): array;

    public function hasSet(string $set): bool;

    /** The whole set, or one version of it. */
    public function snapshot(string $set, ?string $version = null): DocsSnapshot;

    /** One page that may not be stored yet (a draft about to be written). */
    public function inspect(Page $page): PageSnapshot;

    /** Where that page sits and what its links may point at. */
    public function context(Page $page): PageContext;
}
