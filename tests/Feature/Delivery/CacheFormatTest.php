<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Support\CacheFormat;

/*
 * Cached values are plain arrays, and their shape changes between releases.
 * Every LaRecipe key carries the cache format version, so a cache warmed by an
 * older release is never read; and an entry in an unexpected shape is a miss
 * that renders again, never a 500.
 */

/** @return list<string> LaRecipe's keys in the test cache store */
function laRecipeCacheKeys(): array
{
    return array_values(array_filter(array_keys(cache()->getStore()->all(false)), fn (string $key) => str_starts_with($key, 'larecipe')));
}

function warmDocs($test): void
{
    $test->get('/docs/installation')->assertOk();
    $test->get('/docs/installation.md')->assertOk();
    $test->get('/handbook/2.x/deploys', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk();
    $test->get('/docs/_search?q=install')->assertOk();
}

it('puts the cache format version in every LaRecipe cache key', function () {
    warmDocs($this);

    expect(laRecipeCacheKeys())->not->toBeEmpty();

    foreach (laRecipeCacheKeys() as $key) {
        expect(str_starts_with($key, CacheFormat::prefix()) || str_starts_with($key, CacheFormat::psrPrefix()))->toBeTrue("{$key} has no format version");
    }
});

it('treats a cached entry in an unexpected shape as a miss', function (Closure $shape) {
    warmDocs($this);

    foreach (laRecipeCacheKeys() as $key) {
        cache()->getStore()->forever($key, $shape());
    }

    $this->app->forgetScopedInstances();

    $this->get('/docs/installation')->assertOk()->assertSeeText('composer require acme/acme');
    $this->get('/docs/installation.md')->assertOk()->assertSee('composer require acme/acme');
    $this->get('/handbook/2.x/deploys', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->assertJsonPath('page.title', 'Deploys');
    $this->get('/docs/_search?q=install')->assertOk();
    $this->get('/docs/llms.txt')->assertOk()->assertSee('Installation');
})->with([
    'objects from an older release' => fn () => [new PageSummary(new PageRef('docs', 'current', 'en', 'old'), 'Old')],
    'a string' => fn () => 'stale',
    'an array of the wrong shape' => fn () => ['unexpected' => true, 'value' => ['ref' => 'x'], 'html' => 1, 'tree' => 'x', 'references' => 'x'],
    'a list of broken rows' => fn () => [['ref' => 'x'], ['title' => null]],
]);
