<?php

namespace LaRecipe\Domains\Sources\Support;

use LaRecipe\Domains\Sources\Exceptions\UnsafePath;

/**
 * Which paths the docs asset route may serve, for every driver.
 *
 * A docs folder keeps page sources, the manifest and navigation next to its
 * images, and sits inside a repository with dot folders (.git) and dot files
 * (.env). Only the images and files that live with the docs are served: never
 * a page source, never larecipe.json or navigation.json, never a path with a
 * dot segment, and only types with a known content type (ARCHITECTURE.md 22).
 */
final class ServableAsset
{
    private const NEVER = ['larecipe.json', 'navigation.json'];

    /** The clean relative path to serve, or null when it must not be served. */
    public static function path(string $path): ?string
    {
        try {
            $path = PathGuard::relative(ltrim(str_replace('\\', '/', $path), '/'));
        } catch (UnsafePath) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '.')) {
                return null;
            }
        }

        $name = strtolower(basename($path));

        if (str_ends_with($name, '.md') || in_array($name, self::NEVER, true) || MimeTypes::for($name) === MimeTypes::UNKNOWN) {
            return null;
        }

        return $path;
    }

    /** Whether a version (one folder name) is safe to look up assets in. */
    public static function isFolder(string $segment): bool
    {
        try {
            return ! str_starts_with(PathGuard::segment($segment), '.');
        } catch (UnsafePath) {
            return false;
        }
    }
}
