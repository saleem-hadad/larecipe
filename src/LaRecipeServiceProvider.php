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
        $this->loadRoutesFrom(__DIR__.'/routes/LaRecipe.php');
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
    
        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
        }
    }
    
    /**
     * Register the publishable files.
     */
    private function registerPublishableResources()
    {
        $publishablePath = dirname(__DIR__).'/publishable';
        
        $publishable = [
            'config' => [
                "{$publishablePath}/config/LaRecipe.php" => config_path('larecipe.php'),
            ],
        ];
        
        foreach ($publishable as $group => $paths) {
            $this->publishes($paths, $group);
        }
    }
    
    /**
     * Register the package configs.
     */
    public function registerConfigs()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/publishable/config/larecipe.php', 'larecipe'
        );
    }
}