<?php

/*
 * A component's fallback form carries the session's CSRF token, so the page it
 * lands on is personal: shared caches must never store it, while search
 * engines may still index the public page.
 */

use LaRecipe\Facades\LaRecipe;
use LaRecipe\LaRecipe as Manager;
use LaRecipe\Tests\TestCase;

trait UsesContactDocs
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('larecipe.sets.contact', [
            'route' => ['prefix' => 'contact', 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => TestCase::fixture('csrf')],
            'access' => ['mode' => 'public'],
        ]);
    }
}

uses(UsesContactDocs::class);

beforeEach(function () {
    LaRecipe::component('TokenForm')->fallback(fn () => '<form method="post">'.LaRecipe::csrfField().'</form>');
});

it('keeps pages with a session token out of shared caches without hiding them from search', function () {
    $response = $this->get('/contact')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-cache')
        ->and($response->headers->get('X-Robots-Tag'))->toBeNull()
        ->and($response->getContent())->not->toContain(Manager::CSRF_PLACEHOLDER)
        ->toContain('name="_token" value="');
});

it('still caches ordinary public pages publicly', function () {
    expect($this->get('/docs/installation')->headers->get('Cache-Control'))->toContain('public');
});
