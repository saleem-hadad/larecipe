<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Delivery\Events\FeedbackSubmitted;
use LaRecipe\Domains\Delivery\Events\MarkdownServed;
use LaRecipe\Domains\Delivery\Events\PageViewed;
use LaRecipe\Domains\Delivery\Events\SearchPerformed;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

beforeEach(fn () => Sets::withBilling());

it('takes page feedback, dispatches FeedbackSubmitted and stores nothing', function () {
    Event::fake([FeedbackSubmitted::class]);

    $this->postJson('/billing/_feedback', ['path' => 'guides/invoices', 'helpful' => true, 'comment' => '  Clear.  '])->assertNoContent();
    $this->postJson('/handbook/_feedback', ['path' => 'deploys', 'version' => '2.x', 'locale' => 'en', 'helpful' => false])->assertNoContent();

    Event::assertDispatched(FeedbackSubmitted::class, fn (FeedbackSubmitted $event) => $event->set === 'billing' && $event->page->path === 'guides/invoices' && $event->helpful && $event->comment === 'Clear.' && $event->source === 'reader');
    Event::assertDispatched(FeedbackSubmitted::class, fn (FeedbackSubmitted $event) => $event->set === 'handbook' && $event->page->version === '2.x' && ! $event->helpful && $event->comment === null);
});

it('tags thumbs on an Ask AI answer as Ask feedback, with the question it answered', function () {
    Event::fake([FeedbackSubmitted::class]);

    $this->postJson('/billing/_feedback', ['path' => 'guides/invoices', 'helpful' => false, 'question' => '  Can I download invoices?  '])->assertNoContent();

    Event::assertDispatched(FeedbackSubmitted::class, fn (FeedbackSubmitted $event) => $event->source === 'ask'
        && $event->question === 'Can I download invoices?'
        && $event->page->path === 'guides/invoices'
        && ! $event->helpful);

    $this->postJson('/billing/_feedback', ['path' => 'guides/invoices', 'helpful' => true, 'question' => str_repeat('a', 2001)])->assertUnprocessable();
});

it('answers feedback on hidden or missing pages with a 404, and bad input with a 422', function () {
    Event::fake([FeedbackSubmitted::class]);

    $this->postJson('/billing/_feedback', ['path' => 'guides/advanced/roadmap', 'helpful' => true])->assertNotFound();
    $this->postJson('/billing/_feedback', ['path' => 'nope', 'helpful' => true])->assertNotFound();
    $this->postJson('/handbook/_feedback', ['path' => 'deploys', 'version' => '9.x', 'helpful' => true])->assertNotFound();
    $this->postJson('/billing/_feedback', ['path' => 'installation'])->assertUnprocessable();

    Event::assertNotDispatched(FeedbackSubmitted::class);
});

it('puts the feedback endpoint and flag in the boot payload', function () {
    $boot = $this->get('/billing/installation', ['Accept' => 'text/html'])->assertOk();

    preg_match('#<script type="application/json" id="lr-boot">(.*?)</script>#s', $boot->getContent(), $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['feedback']['endpoint'])->toBe(Sets::url('billing/_feedback'))
        ->and($payload['features']['feedback'])->toBeTrue()
        ->and($payload['features']['ask'])->toBeFalse()
        ->and($payload['ask']['enabled'])->toBeFalse();

    Sets::withBilling(['feedback' => ['enabled' => false]]);

    $this->postJson('/billing/_feedback', ['path' => 'installation', 'helpful' => true])->assertStatus(405);
});

it('lists public pages in the sitemap, with hreflang alternates', function () {
    $billing = Sets::local($this->get('/billing/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent());

    expect($billing)->toContain('<loc>/billing/installation</loc>')
        ->toContain('<loc>/billing/guides/advanced/proration</loc>')
        ->toContain('<loc>/billing/changelog</loc>')
        ->not->toContain('roadmap')
        ->not->toContain('drafts');

    $handbook = Sets::local($this->get('/handbook/sitemap.xml')->assertOk()->getContent());

    expect($handbook)->toContain('<xhtml:link rel="alternate" hreflang="ar" href="/handbook/ar/2.x"/>')
        ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="/handbook/2.x"/>')
        ->toContain('<loc>/handbook/1.x</loc>');

    expect(simplexml_load_string($this->get('/billing/sitemap.xml')->getContent()))->not->toBeFalse();
});

it('keeps private sets out of the sitemap entirely', function () {
    Gate::define('viewLarecipe', fn () => true);
    Sets::withBilling(['access' => ['mode' => 'gate']]);

    $this->actingAs(Sets::user())->get('/billing/sitemap.xml')->assertNotFound();
});

it('adds the configured analytics, and refuses unsafe values from content', function () {
    app(LaRecipe::class)->useCspNonce('abc123');

    $html = $this->get('/billing/installation', ['Accept' => 'text/html'])->assertOk()->getContent();

    expect($html)->toContain('<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC1234" nonce="abc123"></script>')
        ->toContain('<script defer data-domain="billing.test" src="https://plausible.io/js/script.js" nonce="abc123"></script>')
        ->toContain("document.addEventListener('larecipe:feedback'")
        ->not->toContain('src="javascript:');

    expect($this->get('/docs/installation', ['Accept' => 'text/html'])->getContent())->not->toContain('larecipe:feedback');
});

it('reports agent Markdown reads next to page views', function () {
    Event::fake([PageViewed::class, MarkdownServed::class]);

    $this->get('/billing/installation.md', ['User-Agent' => 'Claude-User/1.0'])->assertOk();
    $this->get('/billing/installation', ['Accept' => 'text/html'])->assertOk();

    Event::assertDispatchedTimes(PageViewed::class, 2);
    Event::assertDispatchedTimes(MarkdownServed::class, 1);
    Event::assertDispatched(MarkdownServed::class, fn (MarkdownServed $event) => $event->set === 'billing' && $event->page->path === 'installation' && $event->kind === 'agent' && $event->explicit);
});

it('serves the opt-in /ask JSON endpoint for agents without MCP', function () {
    $this->get('/billing/ask?q=invoices')->assertNotFound();

    Sets::withBilling(['agents' => ['ask_endpoint' => true]]);
    Event::fake([SearchPerformed::class]);

    $json = $this->getJson('/billing/ask?q=invoices')->assertOk()->json();

    expect($json['query'])->toBe('invoices')
        ->and(Sets::local($json['results'][0]['markdown_url']))->toBe('/billing/guides/invoices.md')
        ->and($json['results'][0])->toHaveKeys(['title', 'url', 'snippet', 'description', 'score']);

    $this->getJson('/billing/ask')->assertStatus(422);

    Event::assertDispatched(SearchPerformed::class, fn (SearchPerformed $event) => $event->source === 'endpoint' && $event->results > 0);
});

it('serves the JSON Schemas next to the docs', function () {
    $schema = $this->get('/billing/schemas/larecipe.json')->assertOk()->assertHeader('Content-Type', 'application/schema+json')->json();

    expect($schema['$id'])->toBe('https://larecipe.saleem.dev/schemas/3.x/larecipe.json');

    foreach (['navigation', 'frontmatter', 'validate'] as $name) {
        $this->get("/docs/schemas/{$name}.json")->assertOk();
    }

    $this->get('/docs/schemas/secrets.json')->assertNotFound();
});
