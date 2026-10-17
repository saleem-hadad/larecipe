<?php

namespace LaRecipe\Domains\References\Laravel;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Lists the classes in a namespace from Composer's autoloaders (PSR-4 folders
 * and the class map), so "App\Models\*" can be offered as fix hints and written
 * to the snapshot without a hand-maintained list.
 */
final class NamespaceClasses
{
    /** @return list<class-string> */
    public static function in(string $namespace): array
    {
        $namespace = trim($namespace, '\\').'\\';
        $classes = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getClassMap() as $class => $file) {
                if (str_starts_with($class, $namespace)) {
                    $classes[] = $class;
                }
            }

            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                if (! str_starts_with($namespace, $prefix) && ! str_starts_with($prefix, $namespace)) {
                    continue;
                }

                foreach ($directories as $directory) {
                    array_push($classes, ...self::scan($directory, $prefix, $namespace));
                }
            }
        }

        $classes = array_values(array_unique(array_filter($classes, fn (string $class) => class_exists($class))));
        sort($classes);

        return $classes;
    }

    /** @return list<string> */
    private static function scan(string $directory, string $prefix, string $namespace): array
    {
        $base = str_starts_with($namespace, $prefix) ? substr($namespace, strlen($prefix)) : '';
        $root = rtrim($directory, '/').'/'.str_replace('\\', '/', $base);

        if (! is_dir($root)) {
            return [];
        }

        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1, -4);
            $class = $prefix.str_replace('/', '\\', $relative);

            if (str_starts_with($class, $namespace)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
