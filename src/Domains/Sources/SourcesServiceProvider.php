<?php

namespace LaRecipe\Domains\Sources;

use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Content\Contracts\ManifestProvider;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Commands\CacheWarmCommand;
use LaRecipe\Domains\Sources\Commands\MakeDocsMigrationCommand;
use LaRecipe\Domains\Sources\Commands\ProvenanceCommand;
use LaRecipe\Domains\Sources\Commands\PullCommand;
use LaRecipe\Domains\Sources\Commands\SourceExportCommand;
use LaRecipe\Domains\Sources\Commands\SourceImportCommand;
use LaRecipe\Domains\Sources\Commands\SourceMoveCommand;
use LaRecipe\Domains\Sources\Contracts\DocsMigrationRepository;
use LaRecipe\Domains\Sources\Contracts\ProvenanceResolver;
use LaRecipe\Domains\Sources\Contracts\SourceFactory;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Events\ContentChanged;
use LaRecipe\Domains\Sources\Laravel\DatabaseSourceFactory;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationReader;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationWriter;
use LaRecipe\Domains\Sources\Laravel\EloquentDocsMigrationRepository;
use LaRecipe\Domains\Sources\Laravel\GitBranch;
use LaRecipe\Domains\Sources\Laravel\GitProvenanceResolver;
use LaRecipe\Domains\Sources\Laravel\ProvenanceIndexPath;
use LaRecipe\Domains\Sources\Laravel\SourceDrivers;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Sources\Laravel\SourceModels;
use LaRecipe\Domains\Sources\Listeners\WarmChangedSet;
use LaRecipe\Domains\Sources\Mcp\AuthorSession;
use LaRecipe\Domains\Sources\Mcp\DeletePageTool;
use LaRecipe\Domains\Sources\Mcp\MovePageTool;
use LaRecipe\Domains\Sources\Mcp\UpdateManifestTool;
use LaRecipe\Domains\Sources\Mcp\UpdateNavigationTool;
use LaRecipe\Domains\Sources\Mcp\WritePageTool;
use LaRecipe\Domains\Sources\Services\DocsMigrationOperations;
use LaRecipe\Domains\Sources\Services\DocsMigrationRenderer;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\LaRecipe;
use LaRecipe\Support\Contracts\Clock;
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

        $this->app->bind(DocsMigrationRepository::class, EloquentDocsMigrationRepository::class);
        $this->app->bind(GitBranch::class, fn ($app) => new GitBranch($app->basePath()));
        $this->app->bind(DocsMigrationWriter::class, fn ($app) => new DocsMigrationWriter(
            (string) $app['config']->get('larecipe.migrations.path', $app->databasePath('migrations/docs')),
            (string) $app['config']->get('larecipe.default', 'docs'),
            $app->make(GitBranch::class),
            $app->make(DocsMigrationReader::class),
            $app->make(DocsMigrationRenderer::class),
            $app->make(DocsMigrationOperations::class),
            $app->make(DocsMigrationRepository::class),
            $app->make('migration.repository'),
            $app->make(Clock::class),
            $app->basePath('stubs/docs-migration.stub'),
        ));

        // The local stdio server runs in the console; the web endpoint does not.
        $this->app->bind(AuthorSession::class, fn ($app) => new AuthorSession(
            $app,
            $app->make(SourceResolver::class),
            $app->make(Authorizer::class),
            $app->runningInConsole(),
        ));
    }

    public function boot(): void
    {
        $this->app->make(Events::class)->listen(ContentChanged::class, WarmChangedSet::class);

        // The Agents domain composes these into the MCP server (local always; web for database sets with author_tools).
        foreach ([WritePageTool::class, MovePageTool::class, DeletePageTool::class, UpdateNavigationTool::class, UpdateManifestTool::class] as $tool) {
            $this->app->make(LaRecipe::class)->mcpTool($tool, author: true);
        }

        if ($this->usesDatabaseSource()) {
            // The tables load from the package until the host publishes them (to customize them).
            if (glob($this->app->databasePath('migrations/*_create_larecipe_tables.php')) === []) {
                $this->loadMigrationsFrom(Paths::package('database/migrations'));
            }

            // Docs migrations are ordinary migrations: `php artisan migrate` applies them on deploy.
            $this->loadMigrationsFrom((string) $this->app['config']->get('larecipe.migrations.path', $this->app->databasePath('migrations/docs')));
        }

        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                Paths::package('database/migrations') => $this->app->databasePath('migrations'),
            ], 'larecipe-migrations');

            $this->publishes([
                Paths::stubs('docs-migration.stub') => $this->app->basePath('stubs/docs-migration.stub'),
            ], 'larecipe-stubs');

            $this->commands([
                CacheWarmCommand::class,
                MakeDocsMigrationCommand::class,
                ProvenanceCommand::class,
                PullCommand::class,
                SourceExportCommand::class,
                SourceImportCommand::class,
                SourceMoveCommand::class,
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
