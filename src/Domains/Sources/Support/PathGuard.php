<?php

namespace LaRecipe\Domains\Sources\Support;

use LaRecipe\Domains\Sources\Exceptions\UnsafePath;

/**
 * Keeps every write inside the doc set's root. Paths arrive from agents, the
 * admin UI and docs migrations, so they are checked segment by segment before
 * any file is touched (ARCHITECTURE.md 22, "Paths").
 */
final class PathGuard
{
    /** A clean relative path ("guides/webhooks.md"), or UnsafePath. */
    public static function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path) || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw UnsafePath::for($path);
        }

        $segments = explode('/', trim($path, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw UnsafePath::for($path);
            }
        }

        return implode('/', $segments);
    }

    /** One folder name, such as a version or a locale code. */
    public static function segment(string $segment): string
    {
        if (str_contains($segment, '/') || str_contains($segment, '\\')) {
            throw UnsafePath::for($segment);
        }

        return self::relative($segment);
    }

    /** True when $path (existing or not) resolves inside $root, following symlinks of what exists. */
    public static function isInside(string $path, string $root): bool
    {
        $realRoot = realpath($root);

        if ($realRoot === false) {
            return false;
        }

        $existing = $path;
        $rest = '';

        while (! file_exists($existing)) {
            $rest = '/'.basename($existing).$rest;
            $parent = dirname($existing);

            if ($parent === $existing) {
                return false;
            }

            $existing = $parent;
        }

        $real = realpath($existing);

        return $real !== false && ($real === $realRoot || str_starts_with($real.$rest, $realRoot.DIRECTORY_SEPARATOR));
    }
}
