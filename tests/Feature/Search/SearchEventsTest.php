<?php

/*
 * Every search becomes a SearchPerformed event, so hosts can forward what
 * readers look for (and what found nothing) to their own analytics.
 */

use Illuminate\Support\Facades\Event;
use LaRecipe\Domains\Delivery\Events\SearchPerformed;

beforeEach(fn () => Event::fake([SearchPerformed::class]));

it('emits an event for every server search', function () {
    $this->getJson('/docs/_search?q=installation')->assertOk();
    $this->getJson('/docs/_search?q=zzqqxx')->assertOk();

    Event::assertDispatched(SearchPerformed::class, fn ($e) => $e->set === 'docs' && $e->query === 'installation' && $e->source === 'server' && ! $e->zeroResults);
    Event::assertDispatched(SearchPerformed::class, fn ($e) => $e->query === 'zzqqxx' && $e->zeroResults);
});

it('takes the palette searches the browser reports in a batch', function () {
    // CSRF comes from the set's web middleware, as for page feedback (Laravel skips it in tests).
    $this->postJson('/docs/_search/report', ['searches' => [
        ['query' => 'webhooks', 'results' => 3],
        ['query' => 'billing exports', 'results' => 0],
    ]])->assertNoContent();

    Event::assertDispatchedTimes(SearchPerformed::class, 2);
    Event::assertDispatched(SearchPerformed::class, fn ($e) => $e->query === 'billing exports' && $e->source === 'palette' && $e->zeroResults);
});

it('refuses oversized batches', function () {
    $this->postJson('/docs/_search/report', ['searches' => array_fill(0, 21, ['query' => 'x', 'results' => 1])])->assertUnprocessable();
    $this->postJson('/docs/_search/report', ['searches' => [['query' => str_repeat('x', 201), 'results' => 1]]])->assertUnprocessable();

    Event::assertNotDispatched(SearchPerformed::class);
});

it('tells the palette where to report only when it searches in the browser', function () {
    $boot = $this->get('/docs/installation')->assertOk()->getContent();

    expect($boot)->toContain('"report_url":"/docs/_search/report"');

    config()->set('larecipe.sets.docs.search', ['driver' => 'server']);
    app()->forgetScopedInstances();

    expect($this->get('/docs/installation')->getContent())->toContain('"report_url":null');
});
