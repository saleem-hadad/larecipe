<?php

namespace LaRecipe\Domains\Access;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Access\Blocks\GateBlockFilter;
use LaRecipe\Domains\Access\Blocks\GateBlocks;
use LaRecipe\Domains\Access\Commands\ShareCommand;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Access\Laravel\GateAuthorizer;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Access\Services\ShareLinkSigner;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Services\RenderState;
use LaRecipe\LaRecipe;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Authorizer::class, fn ($app) => new GateAuthorizer(
            $app->make(Gate::class),
            $app->make('auth'),
            $app->environment('local'),
        ));

        $this->app->scoped(PageAccess::class);

        // Share links are signed with the app key, so rotating it revokes them all.
        $this->app->singleton(ShareLinkSigner::class, fn ($app) => new ShareLinkSigner((string) $app['config']->get('app.key', '')));
    }

    public function boot(): void
    {
        GateBlocks::register($this->app->make(ComponentRegistry::class));

        $this->app->make(LaRecipe::class)->extendMarkdown(
            fn (EnvironmentBuilderInterface $environment, RenderState $state) => $environment->addEventListener(
                DocumentParsedEvent::class,
                new GateBlockFilter($state),
                GateBlockFilter::PRIORITY,
            ),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([ShareCommand::class]);
        }
    }
}
