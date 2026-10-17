<?php

namespace LaRecipe\Domains\Sync;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sync\Actions\InstallSkillsAction;
use LaRecipe\Domains\Sync\Commands\AffectedCommand;
use LaRecipe\Domains\Sync\Commands\AgentsCommand;
use LaRecipe\Domains\Sync\Commands\AgentsMdCommand;
use LaRecipe\Domains\Sync\Commands\GitHooksCommand;
use LaRecipe\Domains\Sync\Commands\HookCommand;
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
use LaRecipe\Domains\Sync\Services\LoopGuard;
use LaRecipe\Domains\Sync\Services\SkillReferences;
use LaRecipe\LaRecipe;
use LaRecipe\Support\Contracts\Clock;
use LaRecipe\Support\Paths;

/**
 * Wires the Sync domain: drift detection (L0), the AGENTS.md instructions and
 * skills (L1), the pre-push and SessionStart hooks (L2) and their commands.
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
            fn () => $app->make(SourceResolver::class),
            fn () => $app->make('larecipe.set-configs'),
            $app->basePath(),
            (string) ($app['config']->get('larecipe.migrations.path') ?: $app->basePath('database/migrations/docs')),
        ));

        $this->app->bind(Codebase::class, fn ($app) => new LaravelCodebase($app->make(Kernel::class), $app->make('router'), $app->basePath()));
        $this->app->bind(LoopGuard::class, fn ($app) => new LoopGuard($app->make(SyncSettings::class)->stateDirectory, $app->make(Clock::class)));
        $this->app->bind(SkillReferences::class, fn ($app) => new SkillReferences($app->make(ComponentRegistry::class), Paths::resources('schemas')));
        $this->app->bind(InstallSkillsAction::class, fn ($app) => new InstallSkillsAction($app->make(SkillReferences::class), Paths::resources('boost/skills')));
    }

    public function boot(): void
    {
        $this->app->make(LaRecipe::class)->mcpTool(AffectedPagesTool::class, author: true);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AffectedCommand::class,
                SourcesSuggestCommand::class,
                AgentsCommand::class,
                AgentsMdCommand::class,
                HookCommand::class,
                GitHooksCommand::class,
            ]);
        }
    }
}
