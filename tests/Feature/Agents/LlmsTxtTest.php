<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Delivery\Events\LlmsTxtServed;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

beforeEach(fn () => Sets::withBilling());

it('serves a set root llms.txt built from the navigation', function () {
    $response = $this->get('/billing/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $text = Sets::local($response->getContent());

    expect($text)->toStartWith("# Billing\n\n> Billing is a Laravel package for subscriptions and invoices.\n")
        ->toContain('Prefer the guides over the reference pages')
        ->toContain("## Docs\n\n- [Introduction](/billing/index.md): What Billing does and where to start.")
        ->toContain('- [Installation](/billing/installation.md): Add Billing to a Laravel app.')
        ->toContain("## Guides\n\n- [Subscriptions](/billing/guides/subscriptions.md)")
        ->toContain("## Guides / Advanced\n\n- [Proration](/billing/guides/advanced/proration.md)")
        ->toContain("## Reference\n\n- [Reference](/billing/reference.md)")
        ->toContain("## Optional\n\n- [Changelog](/billing/changelog.md)")
        ->toContain('MCP server at `/billing/mcp`')
        ->not->toContain('roadmap')
        ->not->toContain('Draft notes')
        ->not->toContain('status.example.com');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('s-maxage=600')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('lists private pages only for readers the viewLarecipe gate allows, privately cached', function () {
    Gate::define('viewLarecipe', fn ($user = null) => $user !== null);

    expect($this->get('/billing/llms.txt')->getContent())->not->toContain('Billing roadmap');

    $response = $this->actingAs(Sets::user())->get('/billing/llms.txt')->assertOk();

    expect(Sets::local($response->getContent()))->toContain('- [Billing roadmap](/billing/guides/advanced/roadmap.md)')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex');
});

it('lists versions at the root of a versioned set and pages per version', function () {
    $root = Sets::local($this->get('/handbook/llms.txt')->assertOk()->getContent());

    expect($root)->toContain("## Versions\n\n- [2.x (default)](/handbook/2.x/llms.txt): Current version.")
        ->toContain('- [1.x](/handbook/1.x/llms.txt): Maintained: fixes only.')
        ->toContain('- [العربية](/handbook/ar/llms.txt)');

    $version = Sets::local($this->get('/handbook/2.x/llms.txt')->assertOk()->getContent());

    expect($version)->toStartWith('# Handbook 2.x')
        ->toContain("## Start\n\n- [Welcome](/handbook/2.x/index.md)\n- [Deploys](/handbook/2.x/deploys.md)")
        ->toContain('Other versions: 1.x (maintained) at `/handbook/1.x/llms.txt`');

    expect(Sets::local($this->get('/handbook/ar/2.x/llms.txt')->assertOk()->getContent()))
        ->toContain('# Handbook 2.x (العربية)')
        ->toContain('(/handbook/ar/2.x/index.md)');

    expect(Sets::local($this->get('/handbook/ar/llms.txt')->assertOk()->getContent()))
        ->toContain('- [2.x (default)](/handbook/ar/2.x/llms.txt)');
});

it('serves a path-scoped file per group', function () {
    $text = Sets::local($this->get('/billing/guides/llms.txt')->assertOk()->getContent());

    expect($text)->toStartWith('# Billing: Guides')
        ->toContain('- [Invoices](/billing/guides/invoices.md)')
        ->toContain('## Guides / Advanced')
        ->not->toContain('installation.md');

    $this->get('/billing/nope/llms.txt')->assertNotFound()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $this->get('/handbook/9.x/llms.txt')->assertNotFound();
});

it('splits a version past the size limit into one file per group, each under the limit', function () {
    Sets::withBilling(['agents' => ['llms_txt_limit' => 1100]]);

    $index = $this->get('/billing/llms.txt')->assertOk()->getContent();

    expect(mb_strlen($index))->toBeLessThanOrEqual(1100)
        ->and(Sets::local($index))->toContain("## Sections\n\n- [Guides](/billing/guides/llms.txt): 3 pages.")
        ->toContain('- [Reference](/billing/reference/llms.txt): 1 pages.')
        ->toContain('- [Introduction](/billing/index.md)')
        ->not->toContain('subscriptions.md');

    $group = $this->get('/billing/guides/llms.txt')->assertOk()->getContent();

    expect(mb_strlen($group))->toBeLessThanOrEqual(1100)->and($group)->toContain('subscriptions.md');
});

it('keeps the default index well under the AFDocs limit', function () {
    expect(mb_strlen($this->get('/docs/llms.txt')->getContent()))->toBeLessThan(50000);
});

it('reports every served file with the request kind', function () {
    Event::fake([LlmsTxtServed::class]);

    $this->get('/billing/guides/llms.txt', ['User-Agent' => 'Claude-User/1.0'])->assertOk();
    $this->get('/billing/llms.txt', ['User-Agent' => 'GPTBot/1.0'])->assertOk();

    Event::assertDispatched(LlmsTxtServed::class, fn (LlmsTxtServed $event) => $event->set === 'billing' && $event->group === 'guides' && $event->kind === 'agent' && $event->file === 'llms.txt');
    Event::assertDispatched(LlmsTxtServed::class, fn (LlmsTxtServed $event) => $event->group === null && $event->kind === 'crawler');
});

it('answers 404 for a set the caller may not enter', function () {
    Sets::withBilling(['access' => ['mode' => 'auth']]);

    $this->get('/billing/llms.txt')->assertNotFound();
    $this->actingAs(Sets::user())->get('/billing/llms.txt')->assertOk();
});
