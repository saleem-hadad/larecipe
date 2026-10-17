<?php

/*
 * Real 404s with up to five similar pages, in every representation, never
 * hinting at pages the caller may not see.
 */

use LaRecipe\Tests\Feature\Markdown\UsesGuideDocs;

uses(UsesGuideDocs::class);

beforeEach(fn () => $this->defineGateComponents());

it('suggests similar pages in a Markdown 404, linking the .md twins', function () {
    $markdown = $this->get('/guide/billing/webhoks.md')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->getContent();

    expect($markdown)->toContain("Similar pages:\n\n- [Webhooks](http://localhost/guide/billing/webhooks.md): Receive signed events from Acme.");
});

it('suggests similar pages in the HTML and JSON 404s', function () {
    $this->get('/guide/instalation')->assertNotFound()->assertSee('href="http://localhost/guide/installation"', false);

    $suggestions = $this->getJson('/guide/routng', ['Accept' => 'application/vnd.larecipe.page+json'])->assertNotFound()->json('suggestions');

    expect($suggestions[0])->toBe(['title' => 'Routing', 'url' => 'http://localhost/guide/routing', 'description' => 'Protect billing routes with middleware.']);
});

it('suggests at most five pages', function () {
    $suggestions = $this->getJson('/guide/acme-billing-webhooks-routes-commands-installation', ['Accept' => 'application/vnd.larecipe.page+json'])->json('suggestions');

    expect(count($suggestions))->toBeLessThanOrEqual(5)
        ->and(count($suggestions))->toBeGreaterThan(1)
        ->and(count(array_unique(array_column($suggestions, 'url'))))->toBe(count($suggestions));
});

it('never suggests a private page to a guest', function () {
    $markdown = $this->get('/guide/internal.md')->assertNotFound()->getContent();

    expect($markdown)->not->toContain('Internal runbook')
        ->and($this->get('/guide/internal-runbok.md')->getContent())->not->toContain('Internal runbook');
});
