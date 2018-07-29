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
        //
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
    }
}