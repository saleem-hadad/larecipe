<?php

namespace LaRecipe\Domains\Sources\Exceptions;

use RuntimeException;

/**
 * A database set must run behind a persistent cache (the Cached-Database Rule):
 * with the array or null store every request would query the database again.
 */
final class NonPersistentCacheStore extends RuntimeException
{
    public static function forSet(string $set, string $store, string $driver): self
    {
        return new self(
            "The [{$set}] doc set uses the database source, which always runs behind a cache, but its cache store [{$store}] uses the [{$driver}] driver, which keeps nothing between requests. "
            ."Point sets.{$set}.source.cache (or larecipe.cache.store) at a persistent store such as redis, memcached, file or database."
        );
    }
}
