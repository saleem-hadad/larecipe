<?php

namespace LaRecipe\Domains\Sync\Contracts;

use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;

/**
 * What drift detection needs to know about the docs: each set, and each page
 * with the code it documents (`sources:`) and, for repository sets, its file.
 *
 * The Laravel adapter reads through the Sources domain, so database sets work
 * the same way: their `sources:` come from the configured source.
 */
interface DocsCatalog
{
    /** @return list<CatalogSet> */
    public function sets(): array;

    public function set(string $key): ?CatalogSet;

    /** @return list<CatalogPage> */
    public function pages(string $set, string $version, string $locale): array;
}
