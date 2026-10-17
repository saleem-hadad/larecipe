<?php

namespace LaRecipe\Tests\Feature\Sources\Support;

/**
 * Both kinds of set at once, for source moves and the shared contract suite:
 * writable repository copies ("docs", "guide") and a database set ("kb").
 */
trait UsesBothDrivers
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        Fixtures::repositoryEnvironment($app);
        Fixtures::databaseEnvironment($app);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate', ['--database' => 'larecipe'])->run();
    }

    protected function setPath(string $set): string
    {
        return (string) config("larecipe.sets.{$set}.source.path");
    }
}
