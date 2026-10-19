<?php

namespace LaRecipe\Domains\Agents\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Actions\RenderLlmsFullAction;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\Laravel\AgentResponses;
use LaRecipe\Domains\Agents\Laravel\TrafficKind;
use LaRecipe\Domains\Agents\Services\ScopeParser;
use LaRecipe\Domains\Delivery\Events\LlmsTxtServed;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one version's llms-full.txt, or one group's when the version is too
 * large for one file. A versioned set has no whole-site file: its root
 * llms-full.txt redirects to the default version's.
 */
final class LlmsFullController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly ScopeParser $scopes,
        private readonly RenderLlmsFullAction $render,
        private readonly AgentResponses $responses,
        private readonly TrafficKind $kind,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->docs->set($this->current->key($request));

        if ($set === null) {
            return $this->responses->notFound('This llms-full.txt');
        }

        $scope = $this->scopes->parse($set, $request->route('scope'));
        $locale = $scope['locale'] === $set->defaultLocale()->code ? null : $scope['locale'];

        if ($scope['version'] === null) {
            return $scope['rest'] === []
                ? new RedirectResponse($this->links->llmsFull($set, $set->defaultVersion()->id, $locale), 302)
                : $this->responses->notFound('This llms-full.txt');
        }

        $group = $scope['rest'] === [] ? null : implode('/', $scope['rest']);
        $chunks = $this->render->execute($set, $scope['version'], $locale, $group);

        if ($chunks === null) {
            return $this->responses->notFound('This llms-full.txt');
        }

        $this->events->dispatch(new LlmsTxtServed(
            set: $set->key,
            file: 'llms-full.txt',
            kind: $this->kind->forAgentSurface($request),
            version: $scope['version'],
            locale: $scope['locale'],
            group: $group,
            userAgent: $request->userAgent(),
        ));

        $response = new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                echo $chunk;
            }
        }, 200, [
            'Content-Type' => AgentResponses::TEXT,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $this->responses->cache($response, $this->docs->isSharedView($set));
    }
}
