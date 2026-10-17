<?php

namespace LaRecipe\Domains\Search;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\Services\TitleSearchEngine;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchEngine::class, TitleSearchEngine::class);
    }

    public function boot(): void
    {
        //
    }
}
