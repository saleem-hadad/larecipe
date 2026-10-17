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
        // Registered outside the console too, so <Artisan> sees them on web requests.
        $this->commands([UpgradeCommand::class]);
    }
}
