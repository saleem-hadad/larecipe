<?php

namespace SaleemHadad\LaRecipe;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SaleemHadad\LaRecipe\Services\SEOParser;
use SaleemHadad\LaRecipe\Commands\AssetCommand;
use SaleemHadad\LaRecipe\Commands\ThemeCommand;
use SaleemHadad\LaRecipe\Commands\InstallCommand;
use SaleemHadad\LaRecipe\Interfaces\MarkdownParser;
use SaleemHadad\LaRecipe\Interfaces\ISidebarProvider;
use SaleemHadad\LaRecipe\Interfaces\IDocumentProvider;
use SaleemHadad\LaRecipe\Interfaces\IDocumentationService;
use SaleemHadad\LaRecipe\BusinessLogic\GetDocumentRequest;
use SaleemHadad\LaRecipe\BusinessLogic\FileSidebarProvider;
use SaleemHadad\LaRecipe\Services\CommonMarkMarkdownParser;
use SaleemHadad\LaRecipe\BusinessLogic\DocumentationService;
use SaleemHadad\LaRecipe\BusinessLogic\FileDocumentProvider;
use SaleemHadad\LaRecipe\Facades\LaRecipe as LaRecipeFacade;
use SaleemHadad\LaRecipe\Commands\GenerateDocumentationCommand;
use SaleemHadad\LaRecipe\Interfaces\SEOParser as SEOParserContract;
use SaleemHadad\LaRecipe\Interfaces\GetDocumentRequest as GetDocumentRequestContract;

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
            'namespace'  => 'SaleemHadad\LaRecipe\Http\Controllers',
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
                "{$publishablePath}/assets/" => public_path('vendor/saleem-hadad/larecipe/assets'),
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
