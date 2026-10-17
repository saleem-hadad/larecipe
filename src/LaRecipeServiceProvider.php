<?php

namespace LaRecipe;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Support\Contracts\Clock;
use LaRecipe\Support\Contracts\Dispatcher;
use LaRecipe\Support\Laravel\LaravelDispatcher;
use LaRecipe\Support\Laravel\SystemClock;
use LaRecipe\Support\Paths;

/**
 * Registers LaRecipe: the shared support bindings, then each domain's own
 * provider. A domain wires itself up entirely inside its provider, so this list
 * is the only place that knows every domain exists.
 */
final class LaRecipeServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ServiceProvider>> in dependency order */
    public const DOMAINS = [
        Domains\Content\ContentServiceProvider::class,
        Domains\Sources\SourcesServiceProvider::class,
        Domains\Markdown\MarkdownServiceProvider::class,
        Domains\References\ReferencesServiceProvider::class,
        Domains\Access\AccessServiceProvider::class,
        Domains\Search\SearchServiceProvider::class,
        Domains\Validation\ValidationServiceProvider::class,
        Domains\Delivery\DeliveryServiceProvider::class,
        Domains\Agents\AgentsServiceProvider::class,
        Domains\Sync\SyncServiceProvider::class,
        Domains\Onboarding\OnboardingServiceProvider::class,
        Domains\Ask\AskServiceProvider::class,
        Domains\Admin\AdminServiceProvider::class,
        Domains\Upgrade\UpgradeServiceProvider::class,
        Domains\Export\ExportServiceProvider::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(Paths::package('config/larecipe.php'), 'larecipe');

        $this->app->singleton(LaRecipe::class);
        $this->app->bind(Dispatcher::class, LaravelDispatcher::class);
        $this->app->singleton(Clock::class, SystemClock::class);

        foreach (self::DOMAINS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                Paths::package('config/larecipe.php') => config_path('larecipe.php'),
            ], 'larecipe-config');

            $this->publishes([
                Paths::dist() => public_path('vendor/larecipe'),
            ], 'larecipe-assets');
        }
    }
}
