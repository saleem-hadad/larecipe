<?php

namespace LaRecipe\Domains\Search;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Delivery\DTOs\DocsRequest;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Support\NotFoundResponder;
use LaRecipe\Domains\Search\Contracts\SearchDriver;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\Controllers\SearchController;
use LaRecipe\Domains\Search\Controllers\SearchIndexController;
use LaRecipe\Domains\Search\Controllers\SearchReportController;
use LaRecipe\Domains\Search\Laravel\BootPayload;
use LaRecipe\Domains\Search\Laravel\NotFoundSuggestions;
use LaRecipe\Domains\Search\Services\RecordSearchEngine;
use LaRecipe\Domains\Search\Services\SearchDrivers;
use LaRecipe\Domains\Search\Services\SearchIndexRepository;
use LaRecipe\Domains\Search\Services\Tokenizer;
use LaRecipe\Domains\Search\Support\SearchCache;
use LaRecipe\LaRecipe;

final class SearchServiceProvider extends ServiceProvider
{
    /** The locale and version segments in front of `_search` (both optional). */
    private const SCOPE = '[^/]+(?:/[^/]+)?';

    /** {audience}.{fingerprint}.json */
    private const INDEX = '[A-Za-z0-9]+\.[A-Za-z0-9]+\.json';

    public function register(): void
    {
        $this->app->singleton(Tokenizer::class);

        $this->app->scoped(SearchCache::class, fn ($app) => new SearchCache(
            $app['cache']->store($app['config']->get('larecipe.cache.store')),
        ));

        $this->app->scoped(SearchIndexRepository::class);

        $this->app->scoped(SearchDrivers::class, fn ($app) => new SearchDrivers(
            fn (string $set) => (string) ($this->setConfig($app, $set)?->setting('search.driver', SearchDrivers::CLIENT) ?? SearchDrivers::CLIENT),
            fn (string $set, string $name) => $this->makeDriver($app, $set, $name),
        ));

        $this->app->scoped(SearchEngine::class, RecordSearchEngine::class);
    }

    public function boot(): void
    {
        $this->app->make(LaRecipe::class)->routes(function (Router $router, SetConfig $set) {
            $router->get('_search', SearchController::class)
                ->defaults('larecipeSet', $set->key)->name('search');

            $router->get('{scope}/_search', SearchController::class)
                ->where('scope', self::SCOPE)->defaults('larecipeSet', $set->key)->name('search.scoped');

            $router->post('_search/report', SearchReportController::class)
                ->middleware('throttle:larecipe-search-report')->defaults('larecipeSet', $set->key)->name('search.report');

            $router->post('{scope}/_search/report', SearchReportController::class)
                ->where('scope', self::SCOPE)->middleware('throttle:larecipe-search-report')->defaults('larecipeSet', $set->key)->name('search.report.scoped');

            $router->get('_search/{index}', SearchIndexController::class)
                ->where('index', self::INDEX)->defaults('larecipeSet', $set->key)->name('search.index');

            $router->get('{scope}/_search/{index}', SearchIndexController::class)
                ->where(['scope' => self::SCOPE, 'index' => self::INDEX])->defaults('larecipeSet', $set->key)->name('search.index.scoped');
        });

        RateLimiter::for('larecipe-search-report', fn (Request $request) => Limit::perMinute(30)->by('larecipe-search-report|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Both are scoped (rebuilt per request under Octane), so hook every instance as it is built.
        $this->whenResolved(PagePresenter::class, fn (PagePresenter $presenter) => $presenter->extend(
            fn (DocSet $set, array $payload) => $this->app->make(BootPayload::class)($set, $payload),
        ));

        $this->whenResolved(NotFoundResponder::class, fn (NotFoundResponder $responder) => $responder->suggestUsing(
            fn (DocSet $set, DocsRequest $docs) => $this->app->make(NotFoundSuggestions::class)($set, $docs),
        ));
    }

    private function whenResolved(string $abstract, \Closure $callback): void
    {
        $this->app->afterResolving($abstract, $callback);

        if ($this->app->resolved($abstract)) {
            $callback($this->app->make($abstract));
        }
    }

    private function setConfig(Application $app, string $set): ?SetConfig
    {
        return $app->make('larecipe.set-configs')[$set] ?? null;
    }

    /** A custom driver from LaRecipe::extendSearch(), given the set's search config. */
    private function makeDriver(Application $app, string $set, string $name): SearchDriver
    {
        $factory = $app->make(LaRecipe::class)->searchDrivers()[$name] ?? throw new InvalidArgumentException(
            "LaRecipe search driver [{$name}] for set [{$set}] is not registered. Use 'client', 'server', or register it with LaRecipe::extendSearch()."
        );

        $driver = $factory($app, (array) ($this->setConfig($app, $set)?->setting('search', []) ?? []));

        if (! $driver instanceof SearchDriver) {
            throw new InvalidArgumentException("LaRecipe search driver [{$name}] must implement ".SearchDriver::class.'.');
        }

        return $driver;
    }
}
