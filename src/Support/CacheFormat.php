<?php

namespace LaRecipe\Support;

/**
 * The format version of what LaRecipe stores in a cache: source entries, page
 * lists and navigation, rendered pages, highlighted code and search records.
 *
 * Every LaRecipe cache key starts with it, so bumping VERSION when a cached
 * value changes shape makes the old entries unreachable at once: a cache
 * warmed by an older release is never decoded by a newer one. Decoders still
 * treat an entry in an unexpected shape as a miss, never a crash.
 */
final class CacheFormat
{
    public const VERSION = 2;

    /** The prefix for Laravel cache keys: "larecipe:v2:". */
    public static function prefix(): string
    {
        return 'larecipe:v'.self::VERSION.':';
    }

    /** The prefix for PSR-16 keys, which may not contain ":": "larecipe.v2.". */
    public static function psrPrefix(): string
    {
        return 'larecipe.v'.self::VERSION.'.';
    }
}
