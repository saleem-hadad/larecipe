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

        $app['config']->set('larecipe.sets.docs.source.path', Fixtures::copy('docs'));
        $app['config']->set('larecipe.sets.guide', [
            'route' => ['prefix' => 'guide', 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => Fixtures::copy('track-f/guide')],
            'access' => ['mode' => 'public'],
        ]);
    }

    protected function setPath(string $set): string
    {
        return (string) config("larecipe.sets.{$set}.source.path");
    }
}
