<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Contracts\Container\Container;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Drivers\CachedSource;
use LaRecipe\Domains\Sources\Exceptions\NonPersistentCacheStore;
use LaRecipe\Support\Contracts\Clock;

/**
 * Builds the "database" driver: a DatabaseSource, always behind CachedSource.
 *
 * Source config keys: `connection` (null for the default), `cache` (a cache
 * store name; falls back to larecipe.cache.store, then the default store) and
 * `disk` (the filesystem disk for assets; null for the default disk).
 * The array and null cache stores are refused outside tests, because they
 * keep nothing between requests.
 */
final class DatabaseSourceFactory
{
    public function __construct(private readonly Container $app) {}

    public function make(SetConfig $set): CachedSource
    {
        $storeName = $set->source['cache'] ?? $set->setting('cache.store');
        $cache = $this->app->make('cache')->store($storeName);
        $store = $cache->getStore();

        if (($store instanceof ArrayStore || $store instanceof NullStore) && ! $this->app->runningUnitTests()) {
            $name = $storeName ?? (string) $this->app->make('config')->get('cache.default');

            throw NonPersistentCacheStore::forSet($set->key, $name, $store instanceof ArrayStore ? 'array' : 'null');
        }

        $source = new DatabaseSource(
            $set->key,
            isset($set->source['connection']) ? (string) $set->source['connection'] : null,
            $this->app->make(SourceModels::class),
            $this->app->make('db'),
            $this->app->make('filesystem'),
            isset($set->source['disk']) ? (string) $set->source['disk'] : null,
            $this->app->make(Clock::class),
        );

        return new CachedSource($source, new LaravelContentCache($cache), $set->key);
    }
}
