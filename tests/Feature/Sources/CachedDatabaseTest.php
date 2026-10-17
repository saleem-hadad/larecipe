<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Drivers\CachedSource;
use LaRecipe\Domains\Sources\Exceptions\NonPersistentCacheStore;
use LaRecipe\Domains\Sources\Laravel\DatabaseSourceFactory;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Support\CacheFormat;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;

uses(UsesDatabaseSets::class);

beforeEach(function () {
    Fixtures::write('kb', 'index', "# Knowledge base\n\nStart here.\n", ['title' => 'Knowledge base']);
    Fixtures::write('kb', 'guides/deploys', "# Deploys\n\nRun `php artisan migrate` then warm the cache.\n", ['title' => 'Deploys']);
});

it('always serves a database set through the cache', function () {
    expect(app(SourceManager::class)->for('kb'))->toBeInstanceOf(CachedSource::class);
});

it('serves a warm cached page without a single database query', function () {
    $this->get('/kb/guides/deploys')->assertOk()->assertSee('Run <code>php artisan migrate</code>', false);
    $this->get('/kb/guides/deploys.md')->assertOk();

    // A new request: fresh scoped services, the same cache store.
    app()->forgetScopedInstances();
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get('/kb/guides/deploys')->assertOk()->assertSee('Deploys');
    $this->get('/kb/guides/deploys.md')->assertOk()->assertSee('warm the cache');

    expect($queries)->toBe(0);

    // The counter is real: with the cache emptied, the same request reads the database.
    Cache::flush();
    app()->forgetScopedInstances();
    $this->get('/kb/guides/deploys')->assertOk();

    expect($queries)->toBeGreaterThan(0);
});

it('serves new content right after a publish, because the fingerprint moved', function () {
    $this->get('/kb/guides/deploys')->assertOk()->assertSee('warm the cache')->assertDontSee('Roll back');

    Fixtures::write('kb', 'guides/deploys', "# Deploys\n\nRoll back with `migrate:rollback`.\n", ['title' => 'Deploys']);
    app()->forgetScopedInstances();

    $this->get('/kb/guides/deploys')->assertOk()->assertSee('Roll back');
});

it('reads a cached entry in an unexpected shape again from the database', function (mixed $stale) {
    $this->get('/kb/guides/deploys')->assertOk();

    $source = array_filter(array_keys(Cache::getStore()->all(false)), fn (string $key) => str_starts_with($key, CacheFormat::prefix().'source:kb:') && ! str_ends_with($key, ':fingerprint'));
    expect($source)->not->toBeEmpty();

    foreach ($source as $key) {
        Cache::forever($key, ['value' => $stale]);
    }

    app()->forgetScopedInstances();

    $this->get('/kb/guides/deploys')->assertOk()->assertSee('warm the cache');
    $this->get('/kb/guides/deploys.md')->assertOk()->assertSee('warm the cache');
})->with([
    'a string' => 'stale',
    'an array of the wrong shape' => [['ref' => 'x', 'large' => false]],
    'a list of the wrong type' => [[1, 2, 3]],
]);

it('refuses the array and null cache stores outside tests', function () {
    $this->app['env'] = 'production';

    try {
        app(DatabaseSourceFactory::class)->make(SetConfig::fromConfig('kb', ['source' => ['driver' => 'database', 'cache' => 'array']], []));
    } finally {
        $this->app['env'] = 'testing';
    }
})->throws(NonPersistentCacheStore::class, 'keeps nothing between requests');

it('warms every database set with larecipe:cache:warm', function () {
    $this->artisan('larecipe:cache:warm')
        ->expectsOutputToContain('2 pages, fingerprint r')
        ->assertSuccessful();

    $this->artisan('larecipe:cache:warm', ['--set' => ['docs']])
        ->expectsOutputToContain('repository set, nothing to warm')
        ->assertSuccessful();
});
