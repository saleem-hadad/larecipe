<?php

namespace LaRecipe\Domains\Delivery;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Delivery\Commands\ClearCommand;
use LaRecipe\Domains\Delivery\Commands\OptimizeCommand;
use LaRecipe\Domains\Delivery\Laravel\RouteRegistrar;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Services\RenderCache;
use LaRecipe\Domains\Delivery\Services\RequestResolver;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Delivery\Support\NotFoundResponder;
use LaRecipe\Domains\Delivery\Support\ResponseHeaders;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Delivery\Support\ThemeCss;
use LaRecipe\LaRecipe;
use LaRecipe\Support\Paths;

final class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $cache = fn ($app) => $app['cache']->store($app['config']->get('larecipe.cache.store'));

        $this->app->singleton(Assets::class, fn ($app) => new Assets(
            $app->make(LaRecipe::class),
            $app['config']->get('larecipe.ui.dev_server'),
            $app->publicPath(),
        ));

        $this->app->scoped(DocsUrls::class);
        $this->app->scoped(RequestResolver::class);
        $this->app->scoped(SetFromRequest::class);
        $this->app->singleton(ThemeCss::class);
        $this->app->scoped(PageCatalog::class);
        $this->app->when(PageCatalog::class)->needs(Repository::class)->give($cache);
        $this->app->when(RenderCache::class)->needs(Repository::class)->give($cache);
        $this->app->scoped(RenderCache::class);
        $this->app->scoped(PagePresenter::class);
        $this->app->scoped(ResponseHeaders::class);
        $this->app->scoped(NotFoundResponder::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(Paths::resources('views'), 'larecipe');
        $this->loadTranslationsFrom(Paths::resources('lang'), 'larecipe');

        // Registered outside the console too, so <Artisan> sees them on web requests.
        $this->commands([OptimizeCommand::class, ClearCommand::class]);

        if ($this->app->runningInConsole()) {
            // php artisan optimize and optimize:clear run these too, so deploy scripts need no new step.
            $this->optimizes(optimize: 'larecipe:optimize', clear: 'larecipe:clear', key: 'larecipe');
        }

        RateLimiter::for(RouteRegistrar::ASSET_LIMITER, function (Request $request) {
            $parts = array_map('intval', explode(',', (string) $this->app['config']->get('larecipe.assets.rate_limit', '600,1')));

            return Limit::perMinutes(max(1, $parts[1] ?? 1), max(1, $parts[0]))->by('larecipe-assets|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        if (! $this->app->routesAreCached()) {
            $this->app->booted(function () {
                $this->app->make(RouteRegistrar::class)->register($this->app->make('larecipe.set-configs'));
                $this->app['router']->getRoutes()->refreshNameLookups();
                $this->app['router']->getRoutes()->refreshActionLookups();
            });
        }
    }
}
