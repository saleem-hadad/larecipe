<?php

namespace BinaryTorch\LaRecipe;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use BinaryTorch\LaRecipe\Commands\InstallCommand;
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
            'middleware' => 'web',
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
        $this->loadHelpers();

        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
            $this->registerConsoleCommands();
        }
    }

    /**
     * Register the publishable files.
     */
    protected function registerPublishableResources()
    {
        $publishablePath = dirname(__DIR__).'/publishable';

        $publishable = [
            'larecipe_config' => [
                "{$publishablePath}/config/larecipe.php" => config_path('larecipe.php'),
            ],
            'larecipe_assets' => [
                "{$publishablePath}/assets/" => public_path('vendor/binarytorch/larecipe/assets'),
            ],
        ];

        foreach ($publishable as $group => $paths) {
            $this->publishes($paths, $group);
        }
    }

    /**
     * Load helpers.
     */
    protected function loadHelpers()
    {
        foreach (glob(__DIR__.'/Helpers/*.php') as $filename) {
            require_once $filename;
        }
    }

    /**
     * Register the commands accessible from the Console.
     */
    protected function registerConsoleCommands()
    {
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
