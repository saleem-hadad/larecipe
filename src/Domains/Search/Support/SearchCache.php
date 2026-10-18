<?php

namespace LaRecipe\Domains\Search\Support;

use Closure;
use LaRecipe\Support\CacheFormat;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * The few cache operations search needs, over any PSR-16 store (the Laravel
 * adapter passes LaRecipe's cache store). Keys are content-addressed, so
 * entries never need purging; a broken store degrades to rebuilding.
 */
final class SearchCache
{
    public function __construct(private readonly ?CacheInterface $cache = null) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T|mixed
     */
    public function remember(string $key, Closure $build): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $build();
        $this->put($key, $value);

        return $value;
    }

    public function get(string $key): mixed
    {
        try {
            return $this->cache?->get($key);
        } catch (Throwable) {
            return null;
        }
    }

    public function put(string $key, mixed $value): void
    {
        try {
            $this->cache?->set($key, $value);
        } catch (Throwable) {
            // The value was computed; the next request tries the cache again.
        }
    }

    public function forget(string $key): void
    {
        try {
            $this->cache?->delete($key);
        } catch (Throwable) {
            // Nothing to forget.
        }
    }

    /** A PSR-16 safe key: reserved characters ({}()/\@:) never reach the store. */
    public static function key(string $kind, string ...$parts): string
    {
        return CacheFormat::psrPrefix().'search.'.$kind.'.'.hash('xxh128', implode("\0", $parts));
    }
}
