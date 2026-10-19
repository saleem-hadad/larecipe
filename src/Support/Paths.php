<?php

namespace LaRecipe\Support;

/**
 * Locations inside the package itself (views, schemas, stubs, the built UI).
 */
final class Paths
{
    public static function package(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);

        return $path === '' ? $root : $root.'/'.ltrim($path, '/');
    }

    public static function dist(string $path = ''): string
    {
        return self::package('dist'.($path === '' ? '' : '/'.ltrim($path, '/')));
    }

    public static function resources(string $path = ''): string
    {
        return self::package('resources'.($path === '' ? '' : '/'.ltrim($path, '/')));
    }

    public static function stubs(string $path = ''): string
    {
        return self::package('stubs'.($path === '' ? '' : '/'.ltrim($path, '/')));
    }
}
