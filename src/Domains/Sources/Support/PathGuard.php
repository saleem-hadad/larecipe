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

    /**
     * True when $path (existing or not) resolves inside $root. The part that
     * exists is resolved by the filesystem, symlinks included; the part that
     * does not is resolved segment by segment on top of it, so "nope/../../x"
     * cannot climb out while looking like a path inside.
     */
    public static function isInside(string $path, string $root): bool
    {
        $realRoot = realpath($root);

        if ($realRoot === false) {
            return false;
        }

        $existing = $path;
        $rest = [];

        while (! file_exists($existing)) {
            array_unshift($rest, basename($existing));
            $parent = dirname($existing);

            if ($parent === $existing) {
                return false;
            }

            $existing = $parent;
        }

        $resolved = realpath($existing);

        if ($resolved === false) {
            return false;
        }

        foreach ($rest as $segment) {
            $resolved = match ($segment) {
                '', '.' => $resolved,
                '..' => dirname($resolved),
                default => rtrim($resolved, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$segment,
            };
        }

        return $resolved === $realRoot || str_starts_with($resolved, $realRoot.DIRECTORY_SEPARATOR);
    }
}
