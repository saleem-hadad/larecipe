<?php

/*
 * Server search: the endpoint, ranking on the fixture docs, and the same
 * access rules as every page.
 */

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Tests\Feature\Markdown\UsesGuideDocs;

uses(UsesGuideDocs::class);

beforeEach(function () {
    $this->defineGateComponents();

    Gate::define('viewLarecipe', fn ($user = null) => $user !== null);
    Gate::define('view-billing', fn ($user = null) => ($user->plan ?? null) === 'enterprise');
});

function searchPaths($test, string $query): array
{
    return array_column($test->getJson('/guide/_search?q='.urlencode($query))->assertOk()->json('hits'), 'path');
}

it('returns hits with absolute and Markdown URLs', function () {
    $response = $this->getJson('/guide/_search?q=retries')->assertOk();
    $hit = $response->json('hits.0');

    expect($response->json('query'))->toBe('retries')
        ->and($hit['title'])->toBe('Webhooks')
        ->and($hit['heading'])->toBe('Retries')
        ->and($hit['url'])->toBe('http://localhost/guide/billing/webhooks#retries')
        ->and($hit['markdown_url'])->toBe('http://localhost/guide/billing/webhooks.md')
        ->and($hit['snippet'])->toContain('retried')
        ->and($hit['description'])->toBe('Receive signed events from Acme.')
        ->and($response->headers->get('Cache-Control'))->toContain('public');
});

it('finds the expected page in the top three for the shared query set', function (string $query, string $expected) {
    expect(array_slice(array_values(array_unique(searchPaths($this, $query))), 0, 3))->toContain($expected);
})->with([
    ['installation', 'installation'],
    ['composer require', 'installation'],
    ['instalation', 'installation'],
    ['webhooks', 'billing/webhooks'],
    ['signature header', 'billing/webhooks'],
    ['backoff schedule', 'billing/webhooks'],
    ['Route::middleware', 'routing'],
    ['middleware', 'routing'],
    ['snake_case', 'routing'],
    ['orders:prune', 'commands'],
    ['$request->user()', 'commands'],
    ['hasMany', 'commands'],
    ['refund', 'billing/refunds'],
    ['Invoice::refund', 'billing/refunds'],
    ['InvalidAmount', 'billing/refunds'],
    ['migr', 'installation'],
]);

it('limits each page to two sections and honours limit', function () {
    $hits = $this->getJson('/guide/_search?q=webhook&limit=50')->json('hits');
    $perPage = array_count_values(array_column($hits, 'path'));

    expect(max($perPage))->toBeLessThanOrEqual(2)
        ->and($this->getJson('/guide/_search?q=acme&limit=1')->json('hits'))->toHaveCount(1)
        ->and($this->getJson('/guide/_search?q=')->json('hits'))->toBe([]);
});

it('never returns private pages, hidden pages or gated text to guests', function () {
    expect(searchPaths($this, 'zebrafish'))->toBe([])
        ->and(searchPaths($this, 'narwhal'))->toBe([])
        ->and(searchPaths($this, 'quasar dashboard'))->toBe([])
        ->and(searchPaths($this, 'workspace owner'))->toBe(['plans']);
});

it('searches what a signed-in reader may see, and nothing else', function () {
    $this->actingAs(new GenericUser(['id' => 1, 'plan' => 'enterprise', 'remember_token' => null]));

    expect(searchPaths($this, 'zebrafish'))->toBe(['internal'])
        ->and(searchPaths($this, 'quasar dashboard'))->toBe(['plans'])
        ->and(searchPaths($this, 'workspace owner'))->toBe([])
        ->and($this->getJson('/guide/_search?q=zebrafish')->headers->get('Cache-Control'))->toContain('private');
});

it('serves the same engine to other domains through the contract', function () {
    $visible = app(\LaRecipe\Domains\Delivery\Services\PageCatalog::class)->summaries(app(\LaRecipe\LaRecipe::class)->set('guide'), 'current', 'en');
    $hits = app(SearchEngine::class)->search(new SearchQuery('guide', 'current', 'en', 'webhooks', $visible, 3));

    expect($hits)->each->toBeInstanceOf(SearchHit::class)
        ->and($hits[0]->ref->path)->toBe('billing/webhooks');
});

it('searches versions and locales from the scoped endpoint', function () {
    expect(array_column($this->getJson('/handbook/2.x/_search?q=ship')->json('hits'), 'path'))->toBe(['deploys'])
        ->and($this->getJson('/handbook/1.x/_search?q=handbook')->json('hits.0.url'))->toBe('http://localhost/handbook/1.x')
        ->and($this->getJson('/handbook/ar/2.x/_search?q='.urlencode('الدليل'))->json('hits.0.path'))->toBe('index');

    $this->getJson('/guide/billing/_search?q=x')->assertNotFound();
});
