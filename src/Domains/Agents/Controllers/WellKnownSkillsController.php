<?php

namespace LaRecipe\Domains\Agents\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Actions\BuildSkillsIndexAction;
use LaRecipe\Domains\Agents\Laravel\AgentResponses;
use LaRecipe\Domains\Agents\Laravel\TrafficKind;
use LaRecipe\Domains\Delivery\Events\SkillServed;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves /.well-known/agent-skills/index.json (discovery v0.2.0) across every
 * set the caller may enter, so skill installers find a site's skills from its
 * domain alone.
 */
final class WellKnownSkillsController
{
    public function __construct(
        private readonly BuildSkillsIndexAction $build,
        private readonly AgentResponses $responses,
        private readonly TrafficKind $kind,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $index = $this->build->execute();

        $this->events->dispatch(new SkillServed(null, null, $this->kind->forAgentSurface($request), 'index.json', $request->userAgent()));

        $response = new JsonResponse($index->toArray(), 200, ['X-Content-Type-Options' => 'nosniff'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->responses->cache($response, $index->shared);
    }
}
