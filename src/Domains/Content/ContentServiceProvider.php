<?php

namespace LaRecipe\Domains\Content;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Content\Contracts\ManifestProvider;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Content\Services\NavigationResolver;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Content\Services\ZeroConfigNavigation;

final class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FrontmatterParser::class);
        $this->app->singleton(ZeroConfigNavigation::class);
        $this->app->singleton(NavigationResolver::class);

        $this->app->bind('larecipe.set-configs', function ($app) {
            $config = (array) $app['config']->get('larecipe', []);
            $sets = [];

            foreach ((array) ($config['sets'] ?? []) as $key => $set) {
                $sets[$key] = SetConfig::fromConfig((string) $key, (array) $set, $config);
            }

            return $sets;
        });

        $this->app->scoped(SetRegistry::class, fn ($app) => new SetRegistry(
            $app->make('larecipe.set-configs'),
            (string) $app['config']->get('larecipe.default', 'docs'),
            $app->make(ManifestProvider::class),
        ));
    }
}
