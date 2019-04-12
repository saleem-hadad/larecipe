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
     * @return mixed
     */
    public function remember(\Closure $callback, $key)
    {
        if (! config('larecipe.cache.enabled')) {
            return $callback();
        }

        $cachePeriod = $this->checkTtlNeedsChanged(config('larecipe.cache.period'));

        return $this->cache->remember($key, $cachePeriod, $callback);
    }

    /**
     * Checks if minutes need to be changed to seconds
     *
     * @param $ttl
     * @return float|int
     */
    public function checkTtlNeedsChanged($ttl)
    {
        $app_version = explode('.', app()->version());

        if (((int) $app_version[0] == 5 && (int) $app_version[1] >= 8) || $app_version[0] > 5) {
            return config('larecipe.cache.period') * 60;
        }

        return $ttl;
    }
}