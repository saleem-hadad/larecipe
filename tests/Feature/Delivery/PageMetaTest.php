<?php

/*
 * Frontmatter keys LaRecipe does not know often hold notes for the team (an
 * owner, a ticket, a reminder). Only the `meta:` map reaches readers, as
 * page.meta for custom components; everything else stays on the server.
 */

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;

beforeEach(function () {
    $root = Fixtures::temporaryDirectory('meta');
    file_put_contents($root.'/index.md', <<<'MD'
        ---
        title: Billing
        description: Charge customers.
        owner: jane@acme.dev
        notes: Rewrite before the INTERNAL-PRICING launch.
        tickets: [BILL-4821]
        meta:
          audience: developers
          plan: pro
        ---

        # Billing

        How billing works.
        MD);

    Sets::use(['meta' => [
        'route' => ['prefix' => 'meta', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);
});

it('passes only the meta map to the page, never other unknown keys', function () {
    $html = $this->get('/meta')->assertOk()->getContent();
    $json = $this->get('/meta', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk();

    expect($json->json('page.meta'))->toBe(['audience' => 'developers', 'plan' => 'pro']);

    foreach ([$html, $json->getContent(), $this->get('/meta/index.md')->getContent()] as $body) {
        expect($body)->not->toContain('jane@acme.dev')->not->toContain('INTERNAL-PRICING')->not->toContain('BILL-4821');
    }
});

it('reads meta as a map only', function () {
    expect((new Frontmatter(['meta' => ['a', 'b']]))->meta())->toBe([])
        ->and((new Frontmatter(['meta' => 'text']))->meta())->toBe([])
        ->and((new Frontmatter(['owner' => 'jane']))->meta())->toBe([])
        ->and(in_array('meta', Frontmatter::KNOWN_KEYS, true))->toBeTrue();
});
