<?php

namespace LaRecipe\Domains\Validation;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Validation\Commands\ValidateCommand;
use LaRecipe\Domains\Validation\Contracts\DocsInspector;
use LaRecipe\Domains\Validation\Controllers\SchemaController;
use LaRecipe\Domains\Validation\Laravel\SourceInspector;
use LaRecipe\Domains\Validation\Services\RuleRegistry;
use LaRecipe\LaRecipe;

final class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleRegistry::class);
        $this->app->scoped(DocsInspector::class, SourceInspector::class);
    }

    public function boot(): void
    {
        $this->app->make(LaRecipe::class)->routes(function (Router $router, SetConfig $set) {
            $router->get('schemas/{name}.json', SchemaController::class)
                ->where('name', implode('|', SchemaController::NAMES))
                ->name('schema');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ValidateCommand::class]);
        }
    }
}
