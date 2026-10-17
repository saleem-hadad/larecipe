<?php

namespace LaRecipe\Domains\Admin\Laravel;

use Closure;
use Illuminate\Http\Request;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin belongs to whoever the host's manageLarecipe gate lets in, for
 * this set: manageLarecipe($user, $set). Everyone else, signed in or not, gets
 * a 403. The admin is a tool, not content, so unlike a private page it does
 * not pretend to be missing.
 *
 * Without a manageLarecipe gate, the Authorizer lets any signed-in user in
 * locally and nobody elsewhere, like Horizon and Telescope do.
 */
final class AuthorizeAdmin
{
    public const ABILITY = 'manageLarecipe';

    public function __construct(
        private readonly SetFromRequest $sets,
        private readonly Authorizer $authorizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $set = $this->sets->key($request);

        if (! $this->authorizer->allows(self::ABILITY, [$set])) {
            abort(403, "You are not allowed to manage the [{$set}] docs (the manageLarecipe gate).");
        }

        return $next($request);
    }
}
