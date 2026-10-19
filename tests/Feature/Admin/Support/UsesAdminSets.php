<?php

namespace LaRecipe\Tests\Feature\Admin\Support;

use LaRecipe\Tests\Feature\Sources\Support\Fixtures;

/**
 * The admin test environment: the repository sets from TestCase ("docs",
 * "handbook"), plus the "kb" database set at /kb on in-memory SQLite with the
 * package tables migrated, and the manageLarecipe gate letting the manager in.
 */
trait UsesAdminSets
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        Fixtures::databaseEnvironment($app);

        $app->booted(fn () => AdminFixtures::defineGate());
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate', ['--database' => 'larecipe'])->run();
    }
}
