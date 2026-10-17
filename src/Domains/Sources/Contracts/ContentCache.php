<?php

namespace LaRecipe\Domains\Sources\Contracts;

/**
 * The persistent cache in front of a database set (CachedSource). Values are
 * plain arrays and scalars, never objects, so they survive any cache store and
 * Laravel's serializable-classes restriction.
 */
interface ContentCache
{
    /** The stored value, or null when the key is missing. */
    public function get(string $key): mixed;

    /** Store without expiry: keys carry the set's fingerprint, so old ones are simply never read again. */
    public function forever(string $key, mixed $value): void;

    public function forget(string $key): void;
}
