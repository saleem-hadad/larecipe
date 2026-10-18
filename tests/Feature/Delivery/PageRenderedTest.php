<?php

use Illuminate\Support\Facades\Event;
use LaRecipe\Domains\Delivery\Events\PageRendered;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

/*
 * PageRendered (ARCHITECTURE.md 9.7, PHP extension points): fired when a page
 * is rendered, never for a cache hit, with the page and the format only.
 */

it('fires once when an HTML page renders, and not for the cached render after it', function () {
    Event::fake([PageRendered::class]);

    $this->get('/docs/installation')->assertOk();
    $this->app->forgetScopedInstances();
    $this->get('/docs/installation')->assertOk();
    $this->get('/docs/installation', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk();

    Event::assertDispatchedTimes(PageRendered::class, 1);
    Event::assertDispatched(PageRendered::class, fn (PageRendered $event) => $event->set === 'docs'
        && $event->page->path === 'installation'
        && $event->page->version === 'current'
        && $event->format === 'html');
});

it('fires for the agent Markdown twin, which is rendered on every request', function () {
    Event::fake([PageRendered::class]);

    $this->get('/docs/installation.md')->assertOk();

    Event::assertDispatched(PageRendered::class, fn (PageRendered $event) => $event->page->path === 'installation' && $event->format === 'markdown');
});

it('carries nothing about the reader', function () {
    Event::fake([PageRendered::class]);

    $this->actingAs(Sets::user())->get('/docs/installation', ['User-Agent' => 'Claude-User'])->assertOk();

    Event::assertDispatched(PageRendered::class, function (PageRendered $event) {
        expect(array_keys(get_object_vars($event)))->toBe(['set', 'page', 'format', 'contentHash']);

        return true;
    });
});
