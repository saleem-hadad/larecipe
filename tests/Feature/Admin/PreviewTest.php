<?php

use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

uses(UsesAdminSets::class);

$body = <<<'MD'
    # Webhooks

    Acme signs every event. Read [the refunds page](refunds.md) first.

    <Callout type="warning" title="Heads up">
    Verify the signature before you trust the payload.
    </Callout>

    ## Retries

    ```php
    $event = Webhook::verify($payload);
    ```
    MD;

beforeEach(function () {
    $this->actingAs(AdminFixtures::manager());
});

it('renders the preview exactly as readers will get the page', function () use ($body) {
    AdminFixtures::page('billing/refunds', "# Refunds\n");
    AdminFixtures::page('billing/webhooks', $body, ['title' => 'Webhooks']);

    $preview = $this->postJson('/kb/_admin/api/preview', ['path' => 'billing/webhooks', 'frontmatter' => ['title' => 'Webhooks', 'description' => 'A page written by a test.'], 'body' => $body])
        ->assertOk()
        ->json();

    $reader = $this->getJson('/kb/billing/webhooks', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->json();

    expect($preview['html'])->toBe($reader['page']['html'])
        ->and($preview['html'])->toContain('lr-callout')
        ->and($preview['html'])->toContain('href="/kb/billing/refunds"')
        ->and($preview['title'])->toBe('Webhooks')
        ->and(array_column($preview['toc'], 'text'))->toBe(['Retries']);
});

it('previews a page that has no path yet', function () {
    $this->postJson('/kb/_admin/api/preview', ['body' => "# Draft\n\nHello **world**.\n"])
        ->assertOk()
        ->assertJsonPath('title', 'Draft')
        ->assertJsonPath('html', "<p>Hello <strong>world</strong>.</p>\n");
});

it('reports validation issues inline, with line numbers', function () {
    $report = $this->postJson('/kb/_admin/api/validate', ['path' => 'guide', 'frontmatter' => ['title' => 'Guide'], 'body' => "# Guide\n\nText.\n\n<Bogus>x</Bogus>\n"])
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->json();

    $unknown = collect($report['issues'])->firstWhere('rule', 'components.unknown');

    expect($unknown['level'])->toBe('error')
        ->and($unknown['line'])->toBe(5)
        ->and(collect($report['issues'])->firstWhere('rule', 'page.description')['level'] ?? 'warning')->toBe('warning');
});

it('passes a clean page', function () {
    $this->postJson('/kb/_admin/api/validate', ['path' => 'guide', 'frontmatter' => ['title' => 'Guide', 'description' => 'How to.'], 'body' => "# Guide\n\nText.\n"])
        ->assertOk()
        ->assertJsonPath('ok', true);
});
