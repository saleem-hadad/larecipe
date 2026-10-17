<?php

namespace LaRecipe\Domains\Agents;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Registrar;
use LaRecipe\Domains\Agents\Commands\DoctorCommand;
use LaRecipe\Domains\Agents\Commands\McpCommand;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\Laravel\AgentRoutes;
use LaRecipe\Domains\Agents\Laravel\DeliveryDocsLinks;
use LaRecipe\Domains\Agents\Laravel\LaravelAgentDocs;
use LaRecipe\Domains\Agents\Mcp\DocsServer;
use LaRecipe\Domains\Agents\Mcp\McpScope;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Delivery\Controllers\SitemapController;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Support\AnalyticsTags;
use LaRecipe\LaRecipe;

/**
 * Wires the agent surfaces: llms.txt, llms-full.txt, skill.md and the skills
 * index, the MCP server (local and one web endpoint per set) and the doctor.
 *
 * It also registers three reader-traffic pieces Delivery owns (the feedback
 * endpoint and its boot payload, the sitemap, the analytics head tags), so
 * Delivery's own provider stays untouched while the parallel tracks land.
 */
final class AgentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // laravel/mcp is a hard dependency; register it even when package
        // discovery is off, so the local server and web endpoints always exist.
        if (! $this->app->providerIsLoaded(McpServiceProvider::class)) {
            $this->app->register(McpServiceProvider::class);
        }

        $this->app->scoped(AgentDocs::class, LaravelAgentDocs::class);
        $this->app->scoped(DocsLinks::class, DeliveryDocsLinks::class);
        $this->app->scoped(McpScope::class);

        $this->app->when(SitemapController::class)->needs(Cache::class)
            ->give(fn ($app) => $app['cache']->store($app['config']->get('larecipe.cache.store')));
    }

    public function boot(): void
    {
        $larecipe = $this->app->make(LaRecipe::class);

        $larecipe->routes(fn (Router $router, SetConfig $set) => $this->app->make(AgentRoutes::class)->forSet($router, $set));
        $larecipe->globalRoutes(fn (Router $router) => $this->app->make(AgentRoutes::class)->global($router, $this->app->make('larecipe.set-configs')));
        $larecipe->head(fn (DocSet $set) => $this->app->make(AnalyticsTags::class)->render($set));

        $this->app->afterResolving(PagePresenter::class, function (PagePresenter $presenter) {
            $presenter->extend(fn (DocSet $set) => $this->feedbackPayload($set));
        });

        $this->rateLimiters();

        if ($this->app['config']->get('larecipe.mcp.local', true)) {
            $this->app->make(Registrar::class)->local('larecipe', DocsServer::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([McpCommand::class, DoctorCommand::class]);
        }
    }

    /** The feedback widget's part of the boot payload: whether to show it and where it posts. */
    private function feedbackPayload(DocSet $set): array
    {
        $enabled = (bool) $set->config->setting('feedback.enabled', (bool) $this->app['config']->get('larecipe.feedback.enabled', true));

        return [
            'feedback' => ['endpoint' => $enabled ? $this->app->make(DocsUrls::class)->setPath($set, '_feedback') : null],
            'features' => ['feedback' => $enabled],
        ];
    }

    private function rateLimiters(): void
    {
        RateLimiter::for('larecipe-mcp', function (Request $request) {
            $key = (string) ($request->route()?->defaults['larecipeSet'] ?? '');
            $set = $this->app->make('larecipe.set-configs')[$key] ?? null;
            [$max, $minutes] = $this->limit($set?->setting('mcp.web.rate_limit') ?? $this->app['config']->get('larecipe.mcp.web.rate_limit'), 60);

            return Limit::perMinutes($minutes, $max)->by('larecipe-mcp|'.$key.'|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('larecipe-feedback', function (Request $request) {
            [$max, $minutes] = $this->limit($this->app['config']->get('larecipe.feedback.rate_limit'), 20);

            return Limit::perMinutes($minutes, $max)->by('larecipe-feedback|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }

    /** @return array{0: int, 1: int} "requests,minutes" */
    private function limit(mixed $value, int $default): array
    {
        $parts = array_map('intval', explode(',', (string) ($value ?? '')));

        return [max(1, $parts[0] ?? $default ?: $default), max(1, $parts[1] ?? 1)];
    }
}
