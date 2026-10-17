<?php

/*
 * The client index: one immutable JSON file per audience, holding only the
 * records that audience may see (the Same-Rules Rule for search).
 */

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Search\Actions\BuildClientIndexAction;
use LaRecipe\Domains\Search\Actions\ResolveAudienceAction;
use LaRecipe\Tests\Feature\Markdown\UsesGuideDocs;

uses(UsesGuideDocs::class);

beforeEach(function () {
    $this->defineGateComponents();

    Gate::define('viewLarecipe', fn ($user = null) => $user !== null);
    Gate::define('view-billing', fn ($user = null) => ($user->plan ?? null) === 'enterprise');
});

function bootSearch($test, string $url): array
{
    preg_match('#<script type="application/json" id="lr-boot">(.*?)</script>#s', $test->get($url)->getContent(), $boot);

    return json_decode($boot[1], true)['search'];
}

function indexRecords($test, string $url): array
{
    return collect($test->get($url)->assertOk()->json('records'))->keyBy('id')->all();
}

it('adds the reader\'s index URL and the endpoint to the boot payload', function () {
    $search = bootSearch($this, '/guide/installation');

    expect($search['driver'])->toBe('client')
        ->and($search['endpoint'])->toBe('/guide/_search')
        ->and($search['index_url'])->toMatch('#^/guide/_search/[a-f0-9]{12}\.[a-f0-9]{12}\.json$#');
});

it('serves an immutable MiniSearch-ready index', function () {
    $url = bootSearch($this, '/guide')['index_url'];
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('immutable')
        ->and($response->json('fields'))->toBe(['title', 'heading', 'tags', 'text', 'terms'])
        ->and($response->json('storeFields'))->toBe(['title', 'heading', 'path', 'url', 'kind'])
        ->and($response->json('searchOptions.boost.title'))->toBe(4)
        ->and($response->json('searchOptions.prefix'))->toBeTrue();

    $records = indexRecords($this, $url);

    expect($records['billing/webhooks#retries'])->toMatchArray([
        'title' => 'Webhooks',
        'heading' => 'Retries',
        'path' => 'billing/webhooks',
        'url' => '/guide/billing/webhooks#retries',
        'kind' => 'section',
        'tags' => 'events http',
    ])->and($records['commands#the-current-customer']['terms'])->toContain('has many')
        ->and($records['installation']['kind'])->toBe('page');
});

it('never ships private pages, hidden pages or gated blocks to guests', function () {
    $records = indexRecords($this, bootSearch($this, '/guide')['index_url']);
    $json = json_encode($records);

    expect($json)->not->toContain('zebrafish')
        ->and($json)->not->toContain('quasar')
        ->and($json)->not->toContain('narwhal')
        ->and($json)->toContain('Ask your workspace owner');
});

it('gives each audience its own variant', function () {
    $guestUrl = bootSearch($this, '/guide')['index_url'];

    $this->actingAs(new GenericUser(['id' => 1, 'plan' => 'enterprise', 'remember_token' => null]));
    $enterpriseUrl = bootSearch($this, '/guide')['index_url'];
    $response = $this->get($enterpriseUrl)->assertOk();
    $json = $response->getContent();

    expect($enterpriseUrl)->not->toBe($guestUrl)
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($json)->toContain('zebrafish')
        ->and($json)->toContain('quasar')
        ->and($json)->not->toContain('Ask your workspace owner');

    $this->actingAs(new GenericUser(['id' => 2, 'plan' => 'free', 'remember_token' => null]));

    expect(bootSearch($this, '/guide')['index_url'])->not->toBe($enterpriseUrl)->not->toBe($guestUrl);
});

it('redirects a request for someone else\'s audience to the reader\'s own index', function () {
    $this->actingAs(new GenericUser(['id' => 1, 'plan' => 'enterprise', 'remember_token' => null]));
    $enterpriseUrl = bootSearch($this, '/guide')['index_url'];

    auth()->logout();
    $guestUrl = bootSearch($this, '/guide')['index_url'];

    $this->get($enterpriseUrl)->assertRedirect($guestUrl)->assertHeader('Cache-Control', 'no-store, private');

    [$audience] = explode('.', basename($guestUrl));
    $this->get("/guide/_search/{$audience}.000000000000.json")->assertRedirect($guestUrl);
});

it('scopes indexes by version and locale', function () {
    $english = bootSearch($this, '/handbook/2.x/deploys');
    $arabic = bootSearch($this, '/handbook/ar/2.x');

    expect($english['index_url'])->toStartWith('/handbook/2.x/_search/')
        ->and($english['endpoint'])->toBe('/handbook/2.x/_search')
        ->and($arabic['index_url'])->toStartWith('/handbook/ar/2.x/_search/');

    expect($this->get($english['index_url'])->getContent())->toContain('Ship it.')
        ->and($this->get($arabic['index_url'])->getContent())->toContain('الدليل الحالي')
        ->and($this->get($arabic['index_url'])->getContent())->not->toContain('Ship it.');

    $this->get('/handbook/9.x/_search/abc.def.json')->assertNotFound();
    $this->get('/guide/nope/_search/abc.def.json')->assertNotFound();
});

it('reports an index over the size budget', function () {
    $audience = app(ResolveAudienceAction::class)->execute('guide', 'current', 'en', ['index', 'installation'], fn () => false);
    $index = app(BuildClientIndexAction::class)->execute('guide', 'current', 'en', $audience, fn (string $path) => '/'.$path, 0);

    expect($index->overBudget(0))->toBeTrue()
        ->and($index->records)->toBe(4)
        ->and($index->issues[0]['rule'])->toBe('search.budget')
        ->and($index->issues[0]['level'])->toBe('warning');
});

it('points the UI at the endpoint when the set uses the server driver', function () {
    config()->set('larecipe.sets.guide.search', ['driver' => 'server']);

    $search = bootSearch($this, '/guide');

    expect($search)->toBe(['driver' => 'server', 'index_url' => null, 'endpoint' => '/guide/_search']);
    $this->get('/guide/_search/abc.def.json')->assertNotFound();
});
