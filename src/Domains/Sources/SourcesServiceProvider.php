<?php

namespace LaRecipe\Domains\Sources;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Content\Contracts\ManifestProvider;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Laravel\SourceManager;

final class SourcesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SourceManager::class, fn ($app) => new SourceManager($app, $app->make('larecipe.set-configs')));
        $this->app->alias(SourceManager::class, SourceResolver::class);
        $this->app->alias(SourceManager::class, ManifestProvider::class);
    }
}
