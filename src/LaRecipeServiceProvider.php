<?php

namespace BinaryTorch\LaRecipe;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use BinaryTorch\LaRecipe\Commands\AssetCommand;
use BinaryTorch\LaRecipe\Commands\ThemeCommand;
use BinaryTorch\LaRecipe\Commands\InstallCommand;
use BinaryTorch\LaRecipe\Facades\LaRecipe as LaRecipeFacade;
use BinaryTorch\LaRecipe\Commands\GenerateDocumentationCommand;

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
            $this->loadRoutesFrom(__DIR__.'/../routes/LaRecipe.php');
        });
    }

    /**
     * @return array
     */
    protected function routesConfig()
    {
        return [
            'prefix'     => config('larecipe.docs.route'),
            'namespace'  => 'BinaryTorch\LaRecipe\Http\Controllers',
            'domain'     => config('larecipe.domain', null),
            'as'         => 'larecipe.',
            'middleware' => config('larecipe.docs.middleware'),
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
