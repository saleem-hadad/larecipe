<?php

namespace LaRecipe\Domains\Search\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Delivery\Events\SearchPerformed;
use LaRecipe\Domains\Delivery\Support\RequestClassifier;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Search\Laravel\Reader;
use LaRecipe\Domains\Search\Support\SearchScope;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /{prefix}[/{locale}][/{version}]/_search/report
 *
 * Searches answered in the browser (the client index), reported in small
 * batches so hosts get the same SearchPerformed events server searches emit,
 * zero-result flag included. LaRecipe stores nothing; the counts come from the
 * reader's browser, so they are analytics, never a security signal.
 */
final class SearchReportController
{
    public const MAX_SEARCHES = 20;

    private const MAX_QUERY = 200;

    public function __construct(
        private readonly SetFromRequest $current,
        private readonly Reader $reader,
        private readonly Dispatcher $events,
        private readonly RequestClassifier $classifier,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->current->set($request);
        $scope = SearchScope::resolve($set, (string) $request->route('scope', ''));

        if ($scope === null || ! $this->reader->canSearch($set, $scope[0])) {
            throw new NotFoundHttpException;
        }

        $request->validate([
            'searches' => ['required', 'array', 'max:'.self::MAX_SEARCHES],
            'searches.*.query' => ['required', 'string', 'max:'.self::MAX_QUERY],
            'searches.*.results' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        [$version, $locale] = $scope;
        $kind = $this->classifier->classify($request);

        foreach ((array) $request->input('searches') as $search) {
            $query = trim((string) $search['query']);

            if ($query !== '') {
                $this->events->dispatch(new SearchPerformed($set->key, $version, $locale, $query, (int) $search['results'], 'palette', $kind));
            }
        }

        return new Response('', 204, ['Cache-Control' => 'no-store']);
    }
}
