<?php

namespace LaRecipe\Domains\Admin;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Admin\Laravel\AdminRoutes;
use LaRecipe\Domains\Admin\Laravel\IsolatedDoctor;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\LaRecipe;

/**
 * Wires the admin UI (ARCHITECTURE.md 15): the shell and the JSON API under
 * every set's /_admin, contributed through LaRecipe::routes() so they sit
 * inside the set's route group, before the page catch-all.
 *
 * Database sets get the full editor and the review queue. Repository sets get
 * a read-only overview, because their edits belong in git.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(IsolatedDoctor::class);
    }

    public function boot(): void
    {
        $this->app->make(LaRecipe::class)->routes(
            fn (Router $router, SetConfig $set) => (new AdminRoutes)->register($router, $set),
        );
    }
}
