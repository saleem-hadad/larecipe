<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Illuminate\Contracts\Cache\Repository;
use LaRecipe\Domains\Sources\Contracts\ContentCache;

/**
 * CachedSource's cache, backed by one of the host's Laravel cache stores.
 */
final class LaravelContentCache implements ContentCache
{
    public function __construct(private readonly Repository $cache) {}

    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    public function forever(string $key, mixed $value): void
    {
        $this->cache->forever($key, $value);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }
}
