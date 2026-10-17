<?php

namespace LaRecipe\Domains\Export;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Export\Commands\ExportCommand;

final class ExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ExportCommand::class]);
        }
    }
}
