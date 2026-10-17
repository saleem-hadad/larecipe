<?php

namespace LaRecipe\Domains\Sources;

use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Content\Contracts\ManifestProvider;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Commands\CacheWarmCommand;
use LaRecipe\Domains\Sources\Commands\ProvenanceCommand;
use LaRecipe\Domains\Sources\Contracts\ProvenanceResolver;
use LaRecipe\Domains\Sources\Contracts\SourceFactory;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Events\ContentChanged;
use LaRecipe\Domains\Sources\Laravel\DatabaseSourceFactory;
use LaRecipe\Domains\Sources\Laravel\GitProvenanceResolver;
use LaRecipe\Domains\Sources\Laravel\ProvenanceIndexPath;
use LaRecipe\Domains\Sources\Laravel\SourceDrivers;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Sources\Laravel\SourceModels;
use LaRecipe\Domains\Sources\Listeners\WarmChangedSet;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\Support\Paths;

/**
 * Wires the Sources domain: the two built-in drivers (repository, and database
 * behind its cache), the write path's review policy, the cache warm listener,
 * the package tables and the docs migrations path, the commands and the MCP
 * author tools.
 */
final class SourcesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SourceDrivers::class, function () {
            $drivers = new SourceDrivers;
            $drivers->extend('database', fn ($app, SetConfig $set) => $app->make(DatabaseSourceFactory::class)->make($set));

            return $drivers;
        });

        $this->app->scoped(SourceManager::class, fn ($app) => new SourceManager(
            $app,
            $app->make('larecipe.set-configs'),
            $app->make(SourceDrivers::class),
        ));
        $this->app->alias(SourceManager::class, SourceResolver::class);
        $this->app->alias(SourceManager::class, ManifestProvider::class);
        $this->app->alias(SourceManager::class, SourceFactory::class);

        $this->app->singleton(SourceModels::class, fn ($app) => SourceModels::fromConfig((array) $app['config']->get('larecipe.models', [])));
        $this->app->bind(ProvenanceResolver::class, GitProvenanceResolver::class);
        $this->app->singleton(ProvenanceIndexPath::class, fn ($app) => new ProvenanceIndexPath($app->bootstrapPath('cache/larecipe')));
        $this->app->scoped(ReviewPolicy::class, fn ($app) => new ReviewPolicy($app->make('larecipe.set-configs')));
    }

    public function boot(): void
    {
        $this->app->make(Events::class)->listen(ContentChanged::class, WarmChangedSet::class);

        if ($this->usesDatabaseSource()) {
            $this->loadMigrationsFrom(Paths::package('database/migrations'));
        }

        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                Paths::package('database/migrations') => $this->app->databasePath('migrations'),
            ], 'larecipe-migrations');

            $this->commands([
                CacheWarmCommand::class,
                ProvenanceCommand::class,
            ]);
        }
    }

    private function usesDatabaseSource(): bool
    {
        foreach ((array) $this->app['config']->get('larecipe.sets', []) as $set) {
            if (($set['source']['driver'] ?? 'repository') === 'database') {
                return true;
            }
        }

        return false;
    }
}
