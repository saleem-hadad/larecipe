<?php

namespace LaRecipe\Domains\Access;

use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Access\Laravel\GateAuthorizer;
use LaRecipe\Domains\Access\Services\PageAccess;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Authorizer::class, fn ($app) => new GateAuthorizer(
            $app->make(\Illuminate\Contracts\Auth\Access\Gate::class),
            $app->make('auth'),
            $app->environment('local'),
        ));

        $this->app->scoped(PageAccess::class);
    }
}
