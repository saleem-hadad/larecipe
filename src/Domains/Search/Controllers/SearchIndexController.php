<?php

namespace LaRecipe\Domains\Search\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Search\Actions\BuildClientIndexAction;
use LaRecipe\Domains\Search\DTOs\ClientIndex;
use LaRecipe\Domains\Search\Laravel\Reader;
use LaRecipe\Domains\Search\Laravel\SearchUrls;
use LaRecipe\Domains\Search\Services\SearchDrivers;
use LaRecipe\Domains\Search\Support\SearchScope;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /{prefix}[/{locale}][/{version}]/_search/{audience}.{fingerprint}.json
 *
 * The client search index for the reader's own audience. The URL is immutable:
 * new content gets a new fingerprint, so browsers and CDNs may keep it forever.
 * A request for another audience or an old fingerprint is redirected to the
 * reader's current index, so nobody can download records meant for someone else.
 */
final class SearchIndexController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly Reader $reader,
        private readonly SearchUrls $urls,
        private readonly SearchDrivers $drivers,
        private readonly BuildClientIndexAction $build,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $set = $this->current->set($request);
        $scope = SearchScope::resolve($set, (string) $request->route('scope', ''));

        if ($scope === null || $this->drivers->name($set->key) !== SearchDrivers::CLIENT || ! $this->reader->canSearch($set, $scope[0])) {
            throw new NotFoundHttpException;
        }

        [$version, $locale] = $scope;
        $audience = $this->reader->audience($set, $version, $locale);

        if ((string) $request->route('index') !== $audience->fileName()) {
            return new RedirectResponse($this->urls->index($set, $version, $locale, $audience), 302, ['Cache-Control' => 'no-store']);
        }

        $index = $this->build->execute(
            $set->key,
            $version,
            $locale,
            $audience,
            fn (string $path, ?string $anchor) => $this->urls->section($set, $version, $locale, $path, $anchor),
            (int) $set->config->setting('search.budget_kb', ClientIndex::BUDGET_KB),
        );

        return new Response($index->json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => ($this->reader->sharesPublicAnswers($set) ? 'public' : 'private').', max-age=31536000, immutable',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
