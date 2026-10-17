<?php

namespace LaRecipe\Domains\Delivery;

use Illuminate\Support\ServiceProvider;
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
        $this->app->when(PageCatalog::class)->needs(\Illuminate\Contracts\Cache\Repository::class)->give($cache);
        $this->app->when(RenderCache::class)->needs(\Illuminate\Contracts\Cache\Repository::class)->give($cache);
        $this->app->scoped(RenderCache::class);
        $this->app->scoped(PagePresenter::class);
        $this->app->scoped(ResponseHeaders::class);
        $this->app->scoped(NotFoundResponder::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(Paths::resources('views'), 'larecipe');

        if ($this->app->runningInConsole()) {
            $this->commands([Commands\OptimizeCommand::class, Commands\ClearCommand::class]);

            // php artisan optimize and optimize:clear run these too, so deploy scripts need no new step.
            $this->optimizes(optimize: 'larecipe:optimize', clear: 'larecipe:clear', key: 'larecipe');
        }

        if (! $this->app->routesAreCached()) {
            $this->app->booted(function () {
                $this->app->make(RouteRegistrar::class)->register($this->app->make('larecipe.set-configs'));
                $this->app['router']->getRoutes()->refreshNameLookups();
                $this->app['router']->getRoutes()->refreshActionLookups();
            });
        }
    }
}
