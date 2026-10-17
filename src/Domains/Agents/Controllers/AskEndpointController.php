<?php

namespace LaRecipe\Domains\Agents\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\Laravel\AgentResponses;
use LaRecipe\Domains\Agents\Laravel\TrafficKind;
use LaRecipe\Domains\Delivery\Events\SearchPerformed;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /{prefix}/ask?q=: ranked pages with snippets and Markdown URLs as JSON,
 * for agents that can fetch a URL but have no MCP client. Opt in with
 * agents.ask_endpoint; it uses the same ranker and access rules as search_docs.
 */
final class AskEndpointController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly SearchEngine $engine,
        private readonly AgentResponses $responses,
        private readonly TrafficKind $kind,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->docs->set($this->current->key($request));

        if ($set === null) {
            return new JsonResponse(['message' => 'Not found.'], 404);
        }

        $query = trim(mb_substr((string) $request->query('q', ''), 0, 500));

        if ($query === '') {
            return new JsonResponse(['message' => 'Pass what to look for as ?q=, for example ?q=webhooks.'], 422);
        }

        $version = $set->isVersioned() && is_string($request->query('version')) ? $request->query('version') : $set->defaultVersion()->id;
        $locale = is_string($request->query('locale')) && $set->locale($request->query('locale')) !== null ? $request->query('locale') : $set->defaultLocale()->code;

        if ($set->version($version) === null || ! $this->docs->canViewVersion($set, $version)) {
            return new JsonResponse(['message' => "There is no version \"{$version}\"."], 404);
        }

        $limit = max(1, min(20, (int) $request->query('limit', 8)));
        $hits = $this->engine->search(new SearchQuery($set->key, $version, $locale, $query, $this->docs->pages($set, $version, $locale), $limit));
        $results = array_map(fn (SearchHit $hit) => [
            'title' => $hit->title,
            'url' => $this->links->page($set, $hit->ref).($hit->anchor !== null ? '#'.$hit->anchor : ''),
            'markdown_url' => $this->links->page($set, $hit->ref, true),
            'heading' => $hit->heading,
            'snippet' => $hit->snippet,
            'description' => $hit->description,
            'score' => round($hit->score, 4),
        ], array_slice($hits, 0, $limit));

        $this->events->dispatch(new SearchPerformed($set->key, $version, $locale, $query, count($results), 'endpoint', $this->kind->forAgentSurface($request)));

        $response = new JsonResponse(['query' => $query, 'version' => $version, 'locale' => $locale, 'results' => $results], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->responses->cache($response, $this->docs->isSharedView($set));
    }
}
