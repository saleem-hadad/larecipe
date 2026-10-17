<?php

namespace LaRecipe\Tests;

use LaRecipe\LaRecipeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaRecipeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Independent of whatever a local `testbench serve` wrote into the skeleton's .env.
        $app['config']->set('app.url', 'http://localhost');

        // Laravel 13's defaults: cache stores serialize values and unserialize no classes.
        $app['config']->set('cache.stores.array.serialize', true);
        $app['config']->set('cache.serializable_classes', false);

        $app['config']->set('larecipe.sets', [
            'docs' => [
                'route' => ['prefix' => 'docs', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => self::fixture('docs')],
                'access' => ['mode' => 'public'],
            ],
            'handbook' => [
                'route' => ['prefix' => 'handbook', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => self::fixture('handbook')],
                'access' => ['mode' => 'public'],
            ],
        ]);
    }

    public static function fixture(string $path = ''): string
    {
        return __DIR__.'/Fixtures'.($path === '' ? '' : '/'.$path);
    }
}
