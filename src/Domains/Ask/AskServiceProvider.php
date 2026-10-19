<?php

namespace LaRecipe\Domains\Ask;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiServiceProvider;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Ask\Controllers\AskController;
use LaRecipe\Domains\Ask\Services\DailyBudget;
use LaRecipe\Domains\Ask\Support\AiFeatures;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\LaRecipe;

/**
 * Ask AI: on only when laravel/ai is installed and ai.ask.enabled is true for
 * the set. Without either, there is no route and the reader UI hides the panel.
 */
final class AskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiFeatures::class);
        $this->app->when(DailyBudget::class)->needs(Cache::class)
            ->give(fn ($app) => $app['cache']->store($app['config']->get('larecipe.cache.store')));

        if (AiFeatures::available() && ! $this->app->providerIsLoaded(AiServiceProvider::class)) {
            $this->app->register(AiServiceProvider::class);
        }
    }

    public function boot(): void
    {
        if (! AiFeatures::available()) {
            return;
        }

        $this->app->make(LaRecipe::class)->routes(function (Router $router, SetConfig $set) {
            if (! $set->setting('ai.ask.enabled', false)) {
                return;
            }

            $router->post('_ask', AskController::class)->middleware('throttle:larecipe-ask')->name('ask');
            $router->post('{scope}/_ask', AskController::class)->where('scope', '[^/]+(?:/[^/]+)?')->middleware('throttle:larecipe-ask')->name('ask.scoped');
        });

        $this->app->afterResolving(PagePresenter::class, function (PagePresenter $presenter) {
            $presenter->extend(fn (DocSet $set, array $payload) => $this->payload($set, $payload));
        });

        // One bucket per set and reader, shared by the set's /_ask and /{scope}/_ask routes.
        RateLimiter::for('larecipe-ask', function (Request $request) {
            $set = $this->app->make(SetFromRequest::class)->key($request);
            $parts = array_map('intval', explode(',', (string) $this->app['config']->get('larecipe.ai.ask.rate_limit', '10,1')));

            return Limit::perMinutes(max(1, $parts[1] ?? 1), max(1, $parts[0] ?: 10))
                ->by('larecipe-ask|'.$set.'|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }

    /** The Ask panel's part of the boot payload: on for readers the askLarecipe gate allows. */
    private function payload(DocSet $set, array $payload): array
    {
        $enabled = $this->app->make(AiFeatures::class)->askEnabled($set)
            && $this->app->make(PageAccess::class)->allows(PageAccess::ASK, [$set->key]);

        $version = (string) ($payload['version'] ?? $set->defaultVersion()->id);
        $locale = (string) ($payload['locale']['code'] ?? $set->defaultLocale()->code);

        return [
            'ask' => [
                'enabled' => $enabled,
                'endpoint' => $enabled ? $this->app->make(DocsLinks::class)->versionPath($set, $version, '_ask', $locale) : null,
            ],
            'features' => ['ask' => $enabled],
        ];
    }
}
