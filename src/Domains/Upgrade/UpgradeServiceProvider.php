<?php

namespace LaRecipe\Domains\Upgrade;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Upgrade\Commands\UpgradeCommand;

final class UpgradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([UpgradeCommand::class]);
        }
    }
}
