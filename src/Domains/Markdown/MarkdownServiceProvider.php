<?php

namespace LaRecipe\Domains\Markdown;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Markdown\Components\BuiltInComponents;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;
use LaRecipe\Domains\Markdown\Services\CodeHighlighter;
use LaRecipe\Domains\Markdown\Services\MarkdownEnvironmentFactory;
use LaRecipe\Domains\Markdown\Services\PageRenderer;
use LaRecipe\Domains\Markdown\Services\SnippetResolver;

final class MarkdownServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComponentRegistry::class, function () {
            $registry = new ComponentRegistry;
            BuiltInComponents::register($registry);

            return $registry;
        });

        // Highlighted code blocks are cached in LaRecipe's cache store (any store is a PSR-16 cache).
        $this->app->singleton(CodeHighlighter::class, fn ($app) => new CodeHighlighter(
            $app['cache']->store($app['config']->get('larecipe.cache.store')),
        ));

        $this->app->singleton(SnippetResolver::class, fn ($app) => new SnippetResolver(
            fn (string $set) => $this->snippetRoot($app, $set),
        ));

        $this->app->singleton(MarkdownEnvironmentFactory::class, fn ($app) => new MarkdownEnvironmentFactory(
            $app->make(ComponentRegistry::class),
            [],
            $app->make(CodeHighlighter::class),
            $app->make(SnippetResolver::class),
        ));

        $this->app->scoped(PageRenderer::class);
        $this->app->scoped(AgentMarkdownRenderer::class);
    }

    /**
     * Where `<<< @/...` includes resolve for a doc set: the set's own
     * snippets.root, else (repository sets only) the top-level snippets.root or
     * the app's base path. Database sets include nothing unless configured,
     * because their content may come from people outside the codebase.
     */
    private function snippetRoot(Application $app, string $set): ?string
    {
        /** @var array<string, SetConfig> $configs */
        $configs = $app->make('larecipe.set-configs');
        $config = $configs[$set] ?? null;

        if ($config === null) {
            return null;
        }

        $root = $config->setting('snippets.root');

        if ($root === null && $config->driver === 'repository') {
            $root = $app['config']->get('larecipe.snippets.root') ?? $app->basePath();
        }

        return is_string($root) && $root !== '' ? $root : null;
    }
}
