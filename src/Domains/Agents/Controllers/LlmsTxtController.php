<?php

namespace LaRecipe\Domains\Agents\Controllers;

use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Actions\RenderLlmsTxtAction;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Laravel\AgentResponses;
use LaRecipe\Domains\Agents\Laravel\TrafficKind;
use LaRecipe\Domains\Agents\Services\ScopeParser;
use LaRecipe\Domains\Delivery\Events\LlmsTxtServed;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves /{prefix}/llms.txt and the path-scoped indexes under it
 * ({version}/llms.txt, {version}/{group}/llms.txt, with an optional locale).
 *
 * A set, version or group the caller may not see is a plain 404, the same
 * answer a missing one gets.
 */
final class LlmsTxtController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly AgentDocs $docs,
        private readonly ScopeParser $scopes,
        private readonly RenderLlmsTxtAction $render,
        private readonly AgentResponses $responses,
        private readonly TrafficKind $kind,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->docs->set($this->current->key($request));

        if ($set === null) {
            return $this->responses->notFound('This llms.txt');
        }

        $scope = $this->scopes->parse($set, $request->route('scope'));
        $group = $scope['rest'] === [] ? null : implode('/', $scope['rest']);
        $locale = $scope['locale'] === $set->defaultLocale()->code ? null : $scope['locale'];
        $root = $request->route('scope') === null || ($set->isVersioned() && $scope['version'] === null);

        $text = $root
            ? ($group === null ? $this->render->execute($set, null, $locale) : null)
            : $this->render->execute($set, $scope['version'], $locale, $group);

        if ($text === null) {
            return $this->responses->notFound('This llms.txt');
        }

        $this->events->dispatch(new LlmsTxtServed(
            set: $set->key,
            file: 'llms.txt',
            kind: $this->kind->forAgentSurface($request),
            version: $root ? null : $scope['version'],
            locale: $scope['locale'],
            group: $group,
            userAgent: $request->userAgent(),
        ));

        return $this->responses->make($text, AgentResponses::TEXT, $this->docs->isSharedView($set), $request);
    }
}
