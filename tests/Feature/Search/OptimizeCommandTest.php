<?php

/*
 * larecipe:optimize compiles repository sets into the cache (pages,
 * navigation, rendered articles, search records) and larecipe:clear forgets
 * them; both ride along with Laravel's optimize and optimize:clear.
 */

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Delivery\Commands\OptimizeCommand;
use LaRecipe\Domains\Delivery\Services\RenderCache;
use LaRecipe\Domains\Search\Actions\LoadSearchIndexAction;
use LaRecipe\Domains\Search\Services\SearchIndexRepository;
use LaRecipe\Domains\Search\Support\SearchCache;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Markdown\UsesGuideDocs;

uses(UsesGuideDocs::class);

beforeEach(function () {
    $this->defineGateComponents();
    $this->manifest = $this->app->bootstrapPath(OptimizeCommand::MANIFEST);
});

afterEach(function () {
    if (is_dir(dirname($this->manifest))) {
        (new Filesystem)->deleteDirectory(dirname($this->manifest));
    }
});

it('registers with optimize and optimize:clear', function () {
    expect(ServiceProvider::$optimizeCommands['larecipe'] ?? null)->toBe('larecipe:optimize')
        ->and(ServiceProvider::$optimizeClearCommands['larecipe'] ?? null)->toBe('larecipe:clear');
});

it('compiles rendered pages and search records for repository sets', function () {
    $this->artisan('larecipe:optimize', ['--set' => ['guide']])
        ->expectsOutputToContain('LaRecipe docs compiled.')
        ->assertSuccessful();

    $manifest = require $this->manifest;
    $page = app(GetPageAction::class)->execute(app(LaRecipe::class)->set('guide')->ref('billing/webhooks'));
    $key = app(RenderCache::class)->key($page, 'guide|');

    expect($manifest['sets']['guide']['current']['en']['pages'])->toBe(10)
        ->and($manifest['sets']['guide']['current']['en']['records'])->toBeGreaterThan(10)
        ->and($manifest['render_keys'])->toContain($key)
        ->and(cache()->get($key)['html'])->toContain('id="retries"')
        ->and(array_keys($manifest['sets']))->toBe(['guide']);

    $searchKey = SearchCache::key('index', 'guide', 'current', 'en', app(SearchIndexRepository::class)->fingerprint('guide', 'current', 'en'));

    expect(cache()->get($searchKey))->toBeArray();
});

it('serves compiled pages without rendering again', function () {
    $this->artisan('larecipe:optimize', ['--set' => ['guide']])->assertSuccessful();

    // Tag the compiled entry: if the request renders again, the tag is gone.
    $page = app(GetPageAction::class)->execute(app(LaRecipe::class)->set('guide')->ref('billing/webhooks'));
    $key = app(RenderCache::class)->key($page, 'guide|');
    cache()->forever($key, ['html' => '<p>compiled-entry</p>'] + cache()->get($key));

    $this->get('/guide/billing/webhooks')->assertOk()->assertSee('compiled-entry');
});

it('compiles every version and locale', function () {
    $this->artisan('larecipe:optimize', ['--set' => ['handbook']])->assertSuccessful();

    $sets = (require $this->manifest)['sets'];

    expect(array_keys($sets['handbook']))->toBe(['2.x', '1.x'])
        ->and(array_keys($sets['handbook']['2.x']))->toBe(['en', 'ar']);
});

it('runs from php artisan optimize and clears with optimize:clear', function () {
    $this->artisan('optimize', ['--except' => 'config,events,routes,views'])->assertSuccessful();

    expect($this->manifest)->toBeFile();

    $key = (require $this->manifest)['render_keys'][0];
    expect(cache()->has($key))->toBeTrue();

    $this->artisan('larecipe:clear')->assertSuccessful();

    expect($this->manifest)->not->toBeFile()
        ->and(cache()->has($key))->toBeFalse();
});

it('forgets compiled search records on clear', function () {
    $this->artisan('larecipe:optimize', ['--set' => ['guide']])->assertSuccessful();
    $fingerprint = app(SearchIndexRepository::class)->fingerprint('guide', 'current', 'en');

    $this->artisan('larecipe:clear')->assertSuccessful();

    expect(cache()->get(SearchCache::key('index', 'guide', 'current', 'en', $fingerprint)))->toBeNull()
        ->and(app(LoadSearchIndexAction::class)->execute('guide', 'current', 'en')->records)->not->toBe([]);
});
