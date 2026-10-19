<?php

namespace LaRecipe\Support;

/**
 * Dependency-free array helpers for portable domains, which may not use Illuminate\Support.
 */
final class Arr
{
    public static function get(array $data, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    public static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }
}
