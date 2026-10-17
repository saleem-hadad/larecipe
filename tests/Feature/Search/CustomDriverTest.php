<?php

/*
 * Custom search drivers registered with LaRecipe::extendSearch(): they get
 * every record with its required abilities, and every query carries what the
 * reader may see.
 */

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Search\Contracts\SearchDriver;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Domains\Search\DTOs\SearchRecord;
use LaRecipe\Domains\Search\DTOs\SearchResults;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Markdown\UsesGuideDocs;

uses(UsesGuideDocs::class);

beforeEach(function () {
    $this->defineGateComponents();

    $this->driver = new class implements SearchDriver
    {
        /** @var list<array{set: string, version: string, locale: string, records: list<SearchRecord>}> */
        public array $indexed = [];

        /** @var list<SearchQuery> */
        public array $queries = [];

        public array $config = [];

        public function index(string $set, string $version, string $locale, iterable $records): void
        {
            $this->indexed[] = ['set' => $set, 'version' => $version, 'locale' => $locale, 'records' => [...$records]];
        }

        public function search(SearchQuery $query): SearchResults
        {
            $this->queries[] = $query;

            return new SearchResults([new SearchHit(new PageRef($query->set, $query->version, $query->locale, 'installation'), 'From the driver')], 1);
        }

        public function flush(string $set, ?string $version = null): void {}
    };

    $driver = $this->driver;

    app(LaRecipe::class)->extendSearch('memory', function ($app, array $config) use ($driver) {
        $driver->config = $config;

        return $driver;
    });

    config()->set('larecipe.sets.guide.search', ['driver' => 'memory', 'host' => 'search.internal']);
});

it('sends the UI to the endpoint and serves no client index', function () {
    preg_match('#id="lr-boot">(.*?)</script>#s', $this->get('/guide')->getContent(), $boot);

    expect(json_decode($boot[1], true)['search'])->toBe(['driver' => 'memory', 'index_url' => null, 'endpoint' => '/guide/_search']);
    $this->get('/guide/_search/abc.def.json')->assertNotFound();
});

it('searches through the driver with the reader\'s visibility', function () {
    $this->getJson('/guide/_search?q=anything')->assertOk()->assertJsonPath('hits.0.title', 'From the driver');

    $query = $this->driver->queries[0];

    expect($this->driver->config)->toMatchArray(['driver' => 'memory', 'host' => 'search.internal'])
        ->and($query->query)->toBe('anything')
        ->and(array_keys($query->visible))->not->toContain('internal')
        ->and(array_keys($query->visible))->toContain('installation')
        ->and($query->abilities)->toBe([]);
});

it('pushes every record once per content fingerprint, with required abilities', function () {
    $this->getJson('/guide/_search?q=one');
    $this->getJson('/guide/_search?q=two');

    $records = collect($this->driver->indexed[0]['records']);

    expect($this->driver->indexed)->toHaveCount(1)
        ->and($this->driver->indexed[0]['set'])->toBe('guide')
        ->and($records->pluck('path'))->toContain('internal')
        ->and($records->firstWhere('abilities', ['view-billing'])->text)->toContain('quasar')
        ->and($records->firstWhere('abilities', ['!view-billing'])->text)->toContain('workspace owner');
});

it('fails loudly for a driver nobody registered', function () {
    config()->set('larecipe.sets.guide.search', ['driver' => 'typo']);

    app(SearchEngine::class)->search(new SearchQuery('guide', 'current', 'en', 'x', []));
})->throws(InvalidArgumentException::class, 'LaRecipe search driver [typo] for set [guide] is not registered.');
