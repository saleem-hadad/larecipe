<?php

namespace LaRecipe\Domains\Sync;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sync\Commands\AffectedCommand;
use LaRecipe\Domains\Sync\Commands\SourcesSuggestCommand;
use LaRecipe\Domains\Sync\Contracts\Codebase;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Laravel\LaravelCodebase;
use LaRecipe\Domains\Sync\Laravel\SourcesDocsCatalog;
use LaRecipe\Domains\Sync\Mcp\AffectedPagesTool;
use LaRecipe\Domains\Sync\Services\GitCli;
use LaRecipe\Domains\Sync\Services\LocalWorkspace;
use LaRecipe\LaRecipe;

/**
 * Wires the Sync domain: drift detection (L0) and its commands.
 *
 * Path-dependent services are bound, not shared, so they always follow the
 * app's current base path (tests point it at temporary repositories).
 */
final class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SyncSettings::class, fn ($app) => SyncSettings::fromConfig(
            (array) $app['config']->get('larecipe', []),
            $app->storagePath('framework/larecipe/hooks'),
            'bootstrap/cache/larecipe',
        ));

        $this->app->bind(Vcs::class, fn ($app) => new GitCli($app->basePath()));
        $this->app->bind(Workspace::class, fn ($app) => new LocalWorkspace($app->basePath()));

        $this->app->bind(DocsCatalog::class, fn ($app) => new SourcesDocsCatalog(
            $app->make(LaRecipe::class),
            $app->make(SourceResolver::class),
            $app->make('larecipe.set-configs'),
            $app->basePath(),
            (string) ($app['config']->get('larecipe.migrations.path') ?: $app->basePath('database/migrations/docs')),
        ));

        $this->app->bind(Codebase::class, fn ($app) => new LaravelCodebase($app->make(Kernel::class), $app->make('router'), $app->basePath()));
    }

    public function boot(): void
    {
        $this->app->make(LaRecipe::class)->mcpTool(AffectedPagesTool::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AffectedCommand::class,
                SourcesSuggestCommand::class,
            ]);
        }
    }
}
