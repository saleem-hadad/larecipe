<?php

namespace LaRecipe\Tests\Feature\Markdown;

use LaRecipe\Tests\TestCase;

/**
 * Mounts the track A fixture docs as a "guide" doc set at /guide, with snippet
 * includes resolving inside tests/Fixtures/track-a/app.
 */
trait UsesGuideDocs
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('larecipe.sets.guide', [
            'route' => ['prefix' => 'guide', 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => TestCase::fixture('track-a/guide')],
            'access' => ['mode' => 'public'],
            'snippets' => ['root' => TestCase::fixture('track-a/app')],
        ]);
    }

    /** Gate blocks belong to the Access track; these stand-ins only render their children. */
    protected function defineGateComponents(): void
    {
        foreach (['Can', 'Cannot'] as $name) {
            app(\LaRecipe\LaRecipe::class)->component($name)
                ->props(['ability' => ['type' => 'string', 'required' => true]])
                ->fallback(fn (array $props, string $children) => $children)
                ->markdown(fn (array $props, string $children) => $children);
        }
    }
}
