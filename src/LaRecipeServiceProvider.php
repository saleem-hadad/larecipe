<?php

namespace BinaryTorch\LaRecipe;

use Illuminate\Support\ServiceProvider;

class LaRecipeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/LaRecipe.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'larecipe');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(LaRecipe::class, function () {
            return new LaRecipe();
        });

        $this->app->alias(LaRecipe::class, 'LaRecipe');

        $this->registerConfigs();
        $this->loadHelpers();

        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
        }
    }

    /**
     * Register the publishable files.
     */
    private function registerPublishableResources()
    {
        $publishablePath = dirname(__DIR__) . '/publishable';

        $publishable = [
            'config' => [
                "{$publishablePath}/config/LaRecipe.php" => config_path('larecipe.php'),
            ],
            'assets' => [
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
        foreach (glob(__DIR__ . '/Helpers/*.php') as $filename) {
            require_once $filename;
        }
    }

    /**
     * Register the package configs.
     */
    public function registerConfigs()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/publishable/config/larecipe.php',
            'larecipe'
        );
    }
}
