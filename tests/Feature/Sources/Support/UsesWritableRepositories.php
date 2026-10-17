<?php

namespace LaRecipe\Tests\Feature\Sources\Support;

/**
 * Points the "docs" set at a writable copy of tests/Fixtures/docs and adds a
 * "guide" set (versioned, two locales, navigation.json, assets) from a copy of
 * tests/Fixtures/track-f/guide. The fixtures themselves are never written.
 */
trait UsesWritableRepositories
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        Fixtures::repositoryEnvironment($app);
    }

    protected function setPath(string $set): string
    {
        return (string) config("larecipe.sets.{$set}.source.path");
    }
}
