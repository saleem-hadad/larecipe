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

        Fixtures::databaseEnvironment($app);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate', ['--database' => 'larecipe'])->run();
    }
}
