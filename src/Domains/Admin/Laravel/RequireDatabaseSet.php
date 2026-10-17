<?php

namespace LaRecipe\Domains\Admin\Laravel;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editing, reviewing and revisions exist for database sets only. A
 * repository set's pages are Markdown files: they change in the working tree
 * and are reviewed in the pull request, so the admin only shows an overview.
 */
final class RequireDatabaseSet
{
    public function __construct(
        private readonly SetFromRequest $sets,
        private readonly SetRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->sets->key($request);

        if ($this->registry->config($key)->driver !== 'database') {
            return new JsonResponse([
                'message' => "The [{$key}] docs live in the repository, so they are edited in git and reviewed in the pull request. The admin shows them read-only.",
            ], 403);
        }

        return $next($request);
    }
}
