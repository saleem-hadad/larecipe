<?php

namespace LaRecipe\Domains\Onboarding;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Onboarding\Actions\DetectAgentsAction;
use LaRecipe\Domains\Onboarding\Commands\InstallCommand;
use LaRecipe\Domains\Onboarding\Commands\MakePageCommand;
use LaRecipe\Domains\Onboarding\Commands\MakeVersionCommand;
use LaRecipe\Domains\Onboarding\Commands\NavigationFreezeCommand;
use LaRecipe\Domains\Onboarding\Commands\OnboardCommand;

/**
 * Wires the Onboarding domain: `larecipe:install`, `larecipe:onboard` and the
 * authoring commands that scaffold pages, versions and navigation.
 */
final class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Agent CLIs are looked up on this process's PATH; tests point it at fake agents.
        $this->app->bind(DetectAgentsAction::class, fn () => new DetectAgentsAction((string) getenv('PATH')));
    }

    public function boot(): void
    {
        // Registered outside the console too, so <Artisan> sees them on web requests.
        $this->commands([
            InstallCommand::class,
            OnboardCommand::class,
            MakePageCommand::class,
            MakeVersionCommand::class,
            NavigationFreezeCommand::class,
        ]);
    }
}
