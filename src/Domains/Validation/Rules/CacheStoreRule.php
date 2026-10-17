<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * cache.store: a database set on the array or null cache store. A database set
 * always runs behind a cache (the Cached-Database Rule); with a store that
 * forgets everything, every request would query the database.
 */
final class CacheStoreRule implements SetRule
{
    public function check(DocsSnapshot $docs): array
    {
        $settings = $docs->settings;

        if ($settings->driver !== 'database' || $settings->testing || ! in_array($settings->cacheStore, ['array', 'null'], true)) {
            return [];
        }

        return [ValidationIssue::error(
            'cache.store',
            "This database set uses the \"{$settings->cacheStore}\" cache store, which keeps nothing between requests.",
            null,
            null,
            'Set larecipe.cache.store (or LARECIPE_CACHE_STORE) to a persistent store such as redis, database or file.',
        )];
    }
}
