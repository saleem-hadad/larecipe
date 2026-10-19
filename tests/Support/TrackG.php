<?php

namespace LaRecipe\Tests\Support;

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Delivery\Laravel\RouteRegistrar;
use LaRecipe\Domains\References\Services\ReferenceRegistry;

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

    /**
     * A small fixture app (models, events, a controller and a form request) under
     * the TrackGApp namespace, plus the routes, command, tables and listeners live
     * references read. Resolvers are rebuilt so they see this app's config.
     */
    public static function bootFixtureApp(): void
    {
        // Every loader: which one is first depends on how the dependencies were installed.
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('TrackGApp\\', self::fixture('app'));
        }

        $app = app();
        $app['config']->set('larecipe.references.models.allow', ['TrackGApp\\Models\\*']);
        $app['config']->set('larecipe.references.events.namespaces', ['TrackGApp\\Events']);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app->forgetInstance(ReferenceRegistry::class);

        $schema = $app['db']->connection()->getSchemaBuilder();
        $schema->create('orders', function ($table) {
            $table->id();
            $table->string('number')->unique();
            $table->unsignedInteger('total');
            $table->foreignId('customer_id')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
        });

        $router = $app['router'];
        $router->post('orders', ['TrackGApp\Http\Controllers\OrderController', 'store'])->middleware(['web', 'auth'])->name('orders.store');
        $router->get('orders/{order}', fn () => 'order')->middleware('web')->name('orders.show');
        $router->getRoutes()->refreshNameLookups();

        Artisan::command('orders:prune {tenant : The tenant to prune} {--days=30 : Keep orders newer than this many days} {--dry-run : Only report what would be pruned}', fn () => 0)
            ->purpose('Prune old orders');

        $app['events']->listen('TrackGApp\Events\OrderShipped', 'TrackGApp\Listeners\SendShipmentEmail');
        $app['events']->listen('TrackGApp\Events\OrderShipped', ['TrackGApp\Listeners\UpdateWarehouse', 'handle']);
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
