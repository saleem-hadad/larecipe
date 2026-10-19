<?php

namespace LaRecipe\Domains\Search\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaRecipe\Domains\Delivery\Events\SearchPerformed;
use LaRecipe\Domains\Delivery\Support\RequestClassifier;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\Laravel\Reader;
use LaRecipe\Domains\Search\Laravel\SearchUrls;
use LaRecipe\Domains\Search\Support\SearchScope;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /{prefix}[/{locale}][/{version}]/_search?q=webhook+retries&limit=10
 *
 * Server search as JSON: used by the UI with the "server" driver or a custom
 * driver, and by agents that can fetch but have no MCP client. Hits carry
 * absolute URLs and `.md` URLs, so an agent can read the page next.
 */
final class SearchController
{
    private const MAX_QUERY = 200;

    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly SetFromRequest $current,
        private readonly Reader $reader,
        private readonly SearchEngine $search,
        private readonly SearchUrls $urls,
        private readonly Dispatcher $events,
        private readonly RequestClassifier $classifier,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $set = $this->current->set($request);
        $scope = SearchScope::resolve($set, (string) $request->route('scope', ''));

        if ($scope === null || ! $this->reader->canSearch($set, $scope[0])) {
            throw new NotFoundHttpException;
        }

        [$version, $locale] = $scope;
        $query = mb_substr(trim((string) $request->query('q', '')), 0, self::MAX_QUERY);
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->query('limit', 10)));
        $hits = $query === '' ? [] : $this->search->search($this->reader->query($set, $version, $locale, $query, $limit));

        if ($query !== '') {
            $this->events->dispatch(new SearchPerformed($set->key, $version, $locale, $query, count($hits), 'server', $this->classifier->classify($request)));
        }

        return new JsonResponse([
            'query' => $query,
            'version' => $version,
            'locale' => $locale,
            'hits' => array_map(fn (SearchHit $hit) => $hit->toArray() + [
                'url' => $this->urls->absolute($hit->ref, $set, $hit->anchor),
                'markdown_url' => $this->urls->absolute($hit->ref, $set, markdown: true),
            ], $hits),
        ], 200, [
            'Cache-Control' => $this->reader->sharesPublicAnswers($set) ? 'public, max-age=60' : 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
