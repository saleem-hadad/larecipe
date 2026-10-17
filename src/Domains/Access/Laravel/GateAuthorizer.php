<?php

namespace LaRecipe\Domains\Access\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use LaRecipe\Domains\Access\Contracts\Authorizer;

/**
 * Authorizes through the host's own gates and policies.
 *
 * When the host has not defined viewLarecipe, private pages are visible to any
 * signed-in user in local environments only, mirroring Laravel's own defaults
 * for tools such as Horizon. manageLarecipe and askLarecipe follow the same rule.
 */
final class GateAuthorizer implements Authorizer
{
    public function __construct(
        private readonly Gate $gate,
        private readonly Auth $auth,
        private readonly bool $local,
    ) {}

    public function user(): ?object
    {
        return $this->auth->guard()->user();
    }

    public function allows(string $ability, array $arguments = []): bool
    {
        if ($this->gate->has($ability)) {
            return $this->gate->forUser($this->user())->allows($ability, $arguments);
        }

        if (in_array($ability, ['viewLarecipe', 'manageLarecipe', 'askLarecipe'], true)) {
            return $ability === 'askLarecipe' || ($this->local && $this->user() !== null);
        }

        return $this->gate->forUser($this->user())->allows($ability, $arguments);
    }

    public function defines(string $ability): bool
    {
        return $this->gate->has($ability);
    }
}
