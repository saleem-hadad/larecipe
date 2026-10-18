<?php

/*
 * Custom search drivers registered with LaRecipe::extendSearch(): they get
 * every record with its required abilities, and every query carries what the
 * reader may see.
 */

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Search\Contracts\SearchDriver;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Domains\Search\DTOs\SearchRecord;
use LaRecipe\Domains\Search\DTOs\SearchResults;
use LaRecipe\Domains\Search\Services\SearchIndexRepository;
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

        /** @var list<SearchHit>|null hits to answer with instead of the default one */
        public ?array $hits = null;

        public function index(string $set, string $version, string $locale, iterable $records): void
        {
            $this->indexed[] = ['set' => $set, 'version' => $version, 'locale' => $locale, 'records' => [...$records]];
        }

        public function search(SearchQuery $query): SearchResults
        {
            $this->queries[] = $query;

            return new SearchResults($this->hits ?? [new SearchHit(new PageRef($query->set, $query->version, $query->locale, 'installation'), 'From the driver')], 1);
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

    expect(json_decode($boot[1], true)['search'])->toBe(['driver' => 'memory', 'index_url' => null, 'endpoint' => '/guide/_search', 'report_url' => null]);
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

it('drops driver hits the reader may not see, whatever the driver returns', function () {
    $hit = fn (string $path, ?string $anchor = null, ?string $snippet = null, string $version = 'current') => new SearchHit(new PageRef('guide', $version, 'en', $path), ucfirst($path), anchor: $anchor, snippet: $snippet, description: 'From the driver');

    $this->driver->hits = [
        $hit('internal', snippet: 'A private page'),
        $hit('installation', snippet: 'Install it'),
        $hit('plans', 'exports', 'Enterprise plans export invoices as CSV from the quasar dashboard.'),
        $hit('plans', 'not-a-section', 'Made up'),
        $hit('installation', version: '9.x'),
    ];

    $hits = $this->getJson('/guide/_search?q=anything')->assertOk()->json('hits');

    expect(collect($hits)->map(fn ($hit) => $hit['path'].'#'.$hit['anchor'])->all())->toBe(['installation#', 'plans#exports'])
        ->and($hits[0]['snippet'])->toBe('Install it')
        ->and($hits[1]['snippet'])->toBeNull()
        ->and(json_encode($hits))->not->toContain('quasar');
});

it('checks the record a hit names, and keeps its snippet for a reader who may read it', function () {
    $records = collect(app(SearchIndexRepository::class)->get('guide', 'current', 'en')->records);
    $gated = $records->firstWhere('abilities', ['view-billing']);
    $this->driver->hits = [new SearchHit(new PageRef('guide', 'current', 'en', 'plans'), 'Plans', 'Exports', 'exports', 'Export as CSV from the quasar dashboard.', record: $gated->id)];

    $this->getJson('/guide/_search?q=quasar')->assertOk()->assertJsonCount(0, 'hits');

    Gate::define('view-billing', fn ($user) => true);

    $this->actingAs((new User)->forceFill(['id' => 1]))->getJson('/guide/_search?q=quasar')->assertOk()
        ->assertJsonPath('hits.0.snippet', 'Export as CSV from the quasar dashboard.');
});

it('fails loudly for a driver nobody registered', function () {
    config()->set('larecipe.sets.guide.search', ['driver' => 'typo']);

    app(SearchEngine::class)->search(new SearchQuery('guide', 'current', 'en', 'x', []));
})->throws(InvalidArgumentException::class, 'LaRecipe search driver [typo] for set [guide] is not registered.');
