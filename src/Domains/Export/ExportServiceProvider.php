<?php

namespace LaRecipe\Domains\Export;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Export\Commands\ExportCommand;
use LaRecipe\Domains\Export\Laravel\KernelSiteClient;

final class ExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Registered outside the console too, so <Artisan> sees them on web requests.
        $this->commands([ExportCommand::class]);

        // A static host cannot answer Ask AI, take feedback, collect search
        // reports or serve MCP, so the exported pages do not offer them.
        // Registered after the domains that turn them on, so this extender has
        // the last word.
        $this->app->afterResolving(PagePresenter::class, function (PagePresenter $presenter) {
            $presenter->extend(fn () => $this->app['request']->attributes->get(KernelSiteClient::ATTRIBUTE) === true ? [
                'features' => ['ask' => false, 'feedback' => false],
                'urls' => ['mcp' => null],
                'search' => ['report_url' => null],
                'page' => ['actions' => ['mcp_command' => null]],
            ] : []);
        });
    }
}
