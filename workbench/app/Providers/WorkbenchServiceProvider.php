<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * The development app for working on LaRecipe itself: `composer serve` serves the
 * fixture doc sets so the UI can be developed against real pages.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $fixtures = dirname(__DIR__, 3).'/tests/Fixtures';

        config()->set('larecipe.sets', [
            'docs' => [
                'route' => ['prefix' => 'docs', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => $fixtures.'/docs'],
                'access' => ['mode' => 'public'],
            ],
            'handbook' => [
                'route' => ['prefix' => 'handbook', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => $fixtures.'/handbook'],
                'access' => ['mode' => 'public'],
            ],
        ]);
    }
}
