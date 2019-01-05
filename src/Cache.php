<?php

namespace BinaryTorch\LaRecipe;

use Illuminate\Contracts\Cache\Repository;

class Cache
{
    /**
     * The cache implementation.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Create a new documentation instance.
     *
     * @param  Repository  $cache
     * @return void
     */
    public function __construct(Repository $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Wrapper.
     *
     * @param  \Closure  $callback
     * @param  string  $key
     * @return void
     */
    public function remember(\Closure $callback, $key)
    {
        if (! config('larecipe.cache.enabled')) {
            return $callback();
        }

        $cachePeriod = config('larecipe.cache.period');

        return $this->cache->remember($key, $cachePeriod, $callback);
    }
}