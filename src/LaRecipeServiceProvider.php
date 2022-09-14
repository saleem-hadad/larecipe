<?php

namespace BinaryTorch\LaRecipe;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use BinaryTorch\LaRecipe\Services\SEOParser;
use BinaryTorch\LaRecipe\Commands\AssetCommand;
use BinaryTorch\LaRecipe\Commands\ThemeCommand;
use BinaryTorch\LaRecipe\Commands\InstallCommand;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;
use BinaryTorch\LaRecipe\Contracts\ISidebarProvider;
use BinaryTorch\LaRecipe\Contracts\IDocumentProvider;
use BinaryTorch\LaRecipe\Contracts\IDocumentationService;
use BinaryTorch\LaRecipe\BusinessLogic\GetDocumentRequest;
use BinaryTorch\LaRecipe\BusinessLogic\FileSidebarProvider;
use BinaryTorch\LaRecipe\Services\CommonMarkMarkdownParser;
use BinaryTorch\LaRecipe\BusinessLogic\DocumentationService;
use BinaryTorch\LaRecipe\BusinessLogic\FileDocumentProvider;
use BinaryTorch\LaRecipe\Facades\LaRecipe as LaRecipeFacade;
use BinaryTorch\LaRecipe\Commands\GenerateDocumentationCommand;
use BinaryTorch\LaRecipe\Contracts\SEOParser as SEOParserContract;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest as GetDocumentRequestContract;

class LaRecipeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'larecipe');

        Route::group($this->routesConfig(), function () {
            $this->loadRoutesFrom(__DIR__.'/../src/Http/routes.php');
        });
    }

    /**
     * @return array
     */
    protected function routesConfig()
    {
        return [
            'prefix'     => config('larecipe.path'),
            'namespace'  => 'BinaryTorch\LaRecipe\Http\Controllers',
            'as'         => 'larecipe.',
            'middleware' => config('larecipe.middleware'),
        ];
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfigs();

        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
            $this->registerConsoleCommands();
        }

        $this->app->alias('LaRecipe', LaRecipeFacade::class);

        $this->app->singleton('LaRecipe', function () {
            return new LaRecipe();
        });

        $this->app->bind(GetDocumentRequestContract::class, GetDocumentRequest::class);
        $this->app->bind(IDocumentationService::class, DocumentationService::class);
        $this->app->bind(ISidebarProvider::class, FileSidebarProvider::class);
        $this->app->bind(IDocumentProvider::class, FileDocumentProvider::class);
        $this->app->bind(MarkdownParser::class, CommonMarkMarkdownParser::class);
        $this->app->bind(SEOParserContract::class, SEOParser::class);
    }

    /**
     * Register the publishable files.
     */
    protected function registerPublishableResources()
    {
        $publishablePath = dirname(__DIR__) . '/publishable';

        $publishable = [
            'larecipe_config' => [
                "{$publishablePath}/config/larecipe.php" => config_path('larecipe.php'),
            ],
            'larecipe_assets' => [
                "{$publishablePath}/assets/" => public_path('vendor/binarytorch/larecipe/assets'),
            ],
            'larecipe_views' => [
                dirname(__DIR__) . "/resources/views/partials" => resource_path('views/vendor/larecipe/partials'),
            ],
        ];

        foreach ($publishable as $group => $paths) {
            $this->publishes($paths, $group);
        }
    }

    /**
     * Register the commands accessible from the Console.
     */
    protected function registerConsoleCommands()
    {
        $this->commands(AssetCommand::class);
        $this->commands(ThemeCommand::class);
        $this->commands(InstallCommand::class);
        $this->commands(GenerateDocumentationCommand::class);
    }

    /**
     * Register the package configs.
     */
    protected function registerConfigs()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/publishable/config/larecipe.php',
            'larecipe'
        );
    }
}
