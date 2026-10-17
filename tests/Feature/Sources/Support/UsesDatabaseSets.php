<?php

namespace LaRecipe\Tests\Feature\Sources\Support;

/**
 * Adds a database doc set ("kb", mounted at /kb) on an in-memory SQLite
 * connection, with the package tables migrated. Use it in a Pest file with
 * uses(UsesDatabaseSets::class).
 */
trait UsesDatabaseSets
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'larecipe');
        $app['config']->set('database.connections.larecipe', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('filesystems.disks.larecipe-assets', [
            'driver' => 'local',
            'root' => Fixtures::temporaryDirectory('assets'),
        ]);

        $app['config']->set('larecipe.sets.kb', [
            'route' => ['prefix' => 'kb', 'middleware' => ['web']],
            'source' => ['driver' => 'database', 'disk' => 'larecipe-assets'],
            'access' => ['mode' => 'public'],
        ]);

        $app['config']->set('larecipe.migrations.path', Fixtures::temporaryDirectory('docs-migrations'));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate', ['--database' => 'larecipe'])->run();
    }
}
