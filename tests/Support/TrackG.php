<?php

namespace LaRecipe\Tests\Support;

use LaRecipe\Domains\Delivery\Laravel\RouteRegistrar;

/**
 * Test helpers for the access, references, i18n, upgrade and export suites.
 *
 * Doc sets are read from config when the app boots, so a test that needs its own
 * set registers it here: the config, fresh scoped services and the set's routes.
 */
final class TrackG
{
    public static function fixture(string $path = ''): string
    {
        return dirname(__DIR__).'/Fixtures/track-g'.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /** @param array<string, array> $sets set key => set config, as in config/larecipe.php */
    public static function useSets(array $sets): void
    {
        $app = app();
        $app['config']->set('larecipe.sets', array_replace((array) $app['config']->get('larecipe.sets', []), $sets));
        $app->forgetScopedInstances();

        $configs = $app->make('larecipe.set-configs');
        $app->make(RouteRegistrar::class)->register(array_intersect_key($configs, $sets));
        $app['router']->getRoutes()->refreshNameLookups();
        $app['router']->getRoutes()->refreshActionLookups();
    }

    /** A repository set rooted in a track-g fixture folder. */
    public static function set(string $folder, string $prefix, string $mode = 'public'): array
    {
        return [
            'route' => ['prefix' => $prefix, 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => self::fixture($folder)],
            'access' => ['mode' => $mode],
        ];
    }

    /** A copy of a fixture folder in a temporary directory, for tests that write. */
    public static function copy(string $from, ?string $to = null): string
    {
        $to ??= sys_get_temp_dir().'/larecipe-track-g-'.bin2hex(random_bytes(6));
        self::copyDirectory($from, $to);

        return $to;
    }

    public static function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }

    private static function copyDirectory(string $from, string $to): void
    {
        if (! is_dir($to)) {
            mkdir($to, 0777, true);
        }

        foreach (new \FilesystemIterator($from, \FilesystemIterator::SKIP_DOTS) as $item) {
            $target = $to.'/'.$item->getFilename();
            $item->isDir() ? self::copyDirectory($item->getPathname(), $target) : copy($item->getPathname(), $target);
        }
    }
}
