<?php

namespace LaRecipe\Domains\Markdown;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Markdown\Components\BuiltInComponents;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;
use LaRecipe\Domains\Markdown\Services\MarkdownEnvironmentFactory;
use LaRecipe\Domains\Markdown\Services\PageRenderer;

final class MarkdownServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComponentRegistry::class, function () {
            $registry = new ComponentRegistry;
            BuiltInComponents::register($registry);

            return $registry;
        });

        $this->app->singleton(MarkdownEnvironmentFactory::class, fn ($app) => new MarkdownEnvironmentFactory($app->make(ComponentRegistry::class)));
        $this->app->scoped(PageRenderer::class);
        $this->app->singleton(AgentMarkdownRenderer::class);
    }
}
