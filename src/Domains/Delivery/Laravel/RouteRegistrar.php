<?php

namespace LaRecipe\Domains\Delivery\Laravel;

use Illuminate\Routing\Router;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Delivery\Controllers\DocsAssetController;
use LaRecipe\Domains\Delivery\Controllers\PackageAssetController;
use LaRecipe\Domains\Delivery\Controllers\PageController;
use LaRecipe\LaRecipe;

/**
 * Registers the routes of every doc set, plus the global asset route.
 *
 * Each set gets one group under its own prefix (or the domain root) and
 * domain. Routes other domains contribute (llms.txt, MCP, search, admin) are
 * registered inside the group before the page catch-all, so they always win.
 */
final class RouteRegistrar
{
    public function __construct(
        private readonly Router $router,
        private readonly LaRecipe $larecipe,
    ) {}

    /** @param array<string, SetConfig> $sets */
    public function register(array $sets): void
    {
        $this->router->get('larecipe-assets/{build}/{file}', PackageAssetController::class)
            ->where('file', '.*')
            ->name('larecipe.asset');

        foreach ($this->larecipe->globalRouteCallbacks() as $callback) {
            $callback($this->router);
        }

        // Sets mounted at the domain root register last, so their catch-all never
        // swallows another set's prefix.
        uasort($sets, fn (SetConfig $a, SetConfig $b) => ($a->prefix === '') <=> ($b->prefix === ''));

        foreach ($sets as $set) {
            $this->registerSet($set);
        }
    }

    private function registerSet(SetConfig $set): void
    {
        $attributes = array_filter([
            'prefix' => $set->prefix,
            'domain' => $set->domain,
            'middleware' => $set->middleware,
            'as' => "larecipe.{$set->key}.",
        ], fn ($value) => $value !== null && $value !== '');

        $this->router->group($attributes, function (Router $router) use ($set) {
            $router->get('_assets/{version}/{file}', DocsAssetController::class)
                ->where('file', '.*')
                ->defaults('larecipeSet', $set->key)
                ->name('asset');

            // Contributed routes find their set through SetFromRequest (the route name
            // starts with "larecipe.{set}."), so contributors only name their routes.
            foreach ($this->larecipe->routeCallbacks() as $callback) {
                $callback($router, $set);
            }

            $exclude = $set->prefix === '' ? '(?!larecipe-assets/|\.well-known/)' : '';

            $router->get('{path?}', PageController::class)
                ->where('path', $exclude.'.*')
                ->defaults('larecipeSet', $set->key)
                ->name('page');
        });
    }
}
