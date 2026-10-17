<?php

namespace LaRecipe\Domains\References;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Services\RenderState;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\References\Actions\SuggestReferenceAction;
use LaRecipe\Domains\References\Commands\SnapshotCommand;
use LaRecipe\Domains\References\Laravel\ArtisanResolver;
use LaRecipe\Domains\References\Laravel\EventResolver;
use LaRecipe\Domains\References\Laravel\ModelResolver;
use LaRecipe\Domains\References\Laravel\RouteResolver;
use LaRecipe\Domains\References\Markdown\ReferenceComponents;
use LaRecipe\Domains\References\Markdown\ResolveReferences;
use LaRecipe\Domains\References\Services\ReferenceRegistry;
use LaRecipe\Domains\References\Services\SnapshotFallback;
use LaRecipe\Domains\References\Services\SnapshotStore;
use LaRecipe\LaRecipe;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;

/**
 * Wires live references. This provider is the only place in the domain that
 * reads configuration: it hands the model allowlist and the event namespaces to
 * the resolvers, which themselves never read configuration or environment values.
 */
final class ReferencesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SnapshotStore::class, fn ($app) => new SnapshotStore($app->bootstrapPath('cache/larecipe/references.json')));

        $this->app->singleton(ReferenceRegistry::class, function ($app) {
            $snapshot = $app->make(SnapshotStore::class);
            $references = (array) $app['config']->get('larecipe.references', []);

            return new ReferenceRegistry(array_map(fn ($resolver) => new SnapshotFallback($resolver, $snapshot), [
                new RouteResolver($app['router'], $app, $app->getCachedRoutesPath()),
                new ArtisanResolver(fn () => $app->make(Kernel::class)),
                new ModelResolver($app, array_values((array) ($references['models']['allow'] ?? [])), [$app->databasePath('migrations')]),
                new EventResolver($app['events'], array_values((array) ($references['events']['namespaces'] ?? []))),
            ]));
        });
    }

    public function boot(): void
    {
        ReferenceComponents::register($this->app->make(ComponentRegistry::class), fn () => $this->app->make(ResolveReferenceAction::class));

        $this->app->make(LaRecipe::class)->extendMarkdown(
            fn (EnvironmentBuilderInterface $environment, RenderState $state) => $environment->addEventListener(
                DocumentParsedEvent::class,
                new ResolveReferences($state, $this->app->make(ResolveReferenceAction::class), $this->app->make(SuggestReferenceAction::class)),
                ResolveReferences::PRIORITY,
            ),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([SnapshotCommand::class]);
        }
    }
}
