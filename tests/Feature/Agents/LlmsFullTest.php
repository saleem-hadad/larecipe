<?php

use Illuminate\Support\Facades\Event;
use LaRecipe\Domains\Delivery\Events\LlmsTxtServed;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

beforeEach(fn () => Sets::withBilling());

it('streams the agent Markdown of every visible page in reading order', function () {
    $response = $this->get('/billing/llms-full.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $text = Sets::local($response->streamedContent());

    expect($text)->toStartWith("# Billing: full text\n")
        ->toContain('This file holds the Markdown of 7 pages')
        ->toContain("title: Installation\n")
        ->toContain('composer require acme/billing')
        ->toContain('Invoices download as PDF.')
        ->not->toContain('Usage-based pricing')
        ->not->toContain('> Docs index:');

    expect(strpos($text, 'title: Introduction'))->toBeLessThan(strpos($text, 'title: Installation'))
        ->and(strpos($text, 'title: Installation'))->toBeLessThan(strpos($text, 'title: Subscriptions'))
        ->and(strpos($text, 'title: Reference'))->toBeLessThan(strpos($text, 'title: Changelog'));
});

it('redirects the root of a versioned set to the default version', function () {
    $this->get('/handbook/llms-full.txt')->assertRedirect(Sets::url('handbook/2.x/llms-full.txt'));

    expect($this->get('/handbook/2.x/llms-full.txt')->assertOk()->streamedContent())
        ->toContain('Ship it.')
        ->not->toContain('The old handbook.');
});

it('splits by group past its cap and lists what it left out', function () {
    Sets::withBilling(['agents' => ['llms_full_limit' => 900]]);

    $index = Sets::local($this->get('/billing/llms-full.txt')->assertOk()->streamedContent());

    expect($index)->toContain('## More pages, one file per section')
        ->toContain('- [Guides](/billing/guides/llms-full.txt): 3 pages.')
        ->not->toContain('Invoices download as PDF.');

    $guides = Sets::local($this->get('/billing/guides/llms-full.txt')->assertOk()->streamedContent());

    expect($guides)->toContain('# Billing: Guides: full text')
        ->toContain('Subscriptions renew at the end of each period.')
        ->toContain('## Pages not included')
        ->toContain('(/billing/guides/advanced/proration.md)');

    $this->get('/billing/nowhere/llms-full.txt')->assertNotFound();
});

it('reports llms-full.txt fetches', function () {
    Event::fake([LlmsTxtServed::class]);

    $this->get('/handbook/2.x/llms-full.txt')->assertOk();

    Event::assertDispatched(LlmsTxtServed::class, fn (LlmsTxtServed $event) => $event->file === 'llms-full.txt' && $event->version === '2.x' && $event->set === 'handbook');
});
