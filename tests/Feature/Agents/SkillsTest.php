<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Delivery\Events\SkillServed;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use Symfony\Component\Yaml\Yaml;

beforeEach(fn () => Sets::withBilling());

it('generates a portable skill.md for each set', function () {
    $response = $this->get('/billing/skill.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    $text = Sets::local($response->getContent());

    preg_match('/\A---\n(.*?)\n---\n/s', $text, $matches);
    $frontmatter = Yaml::parse($matches[1]);

    expect(array_keys($frontmatter))->toBe(['name', 'description'])
        ->and($frontmatter['name'])->toBe('billing')
        ->and($frontmatter['name'])->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/')
        ->and(mb_strlen($frontmatter['description']))->toBeLessThanOrEqual(1024)
        ->and($frontmatter['description'])->toStartWith('Use when working with Billing: Charge customers from a Laravel app.');

    expect($text)->toContain('# Billing')
        ->toContain('Prefer the guides over the reference pages')
        ->toContain('call `search_docs` on the MCP server at /billing/mcp')
        ->toContain('- Docs index: /billing/llms.txt')
        ->toContain("### Guides\n\n- [Subscriptions](/billing/guides/subscriptions.md)")
        ->not->toContain('roadmap');
});

it('serves hand-written skills and their reference files, and nothing outside them', function () {
    $this->get('/billing/skills/billing-helper/SKILL.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('name: billing-helper', false);

    $this->get('/billing/skills/billing-helper/references/api.md')->assertOk()->assertSee('starts a subscription');

    $this->get('/billing/skills/billing-helper/references/nope.md')->assertNotFound();
    $this->get('/billing/skills/billing-helper/references/../../../larecipe.json')->assertNotFound();
    $this->get('/billing/skills/billing-helper/references/%2e%2e/SKILL.md')->assertNotFound();
    $this->get('/billing/skills/unknown/SKILL.md')->assertNotFound();
});

it('lets a hand-written skill named like the set replace the generated one', function () {
    $directory = Sets::fixture('billing/skills/billing');
    @mkdir($directory, 0777, true);
    file_put_contents($directory.'/SKILL.md', "---\nname: billing\ndescription: Hand-written.\n---\n\n# Written by hand\n");

    try {
        $this->get('/billing/skill.md')->assertOk()->assertSee('# Written by hand');
    } finally {
        @unlink($directory.'/SKILL.md');
        @rmdir($directory);
    }
});

it('publishes every public set\'s skills at /.well-known/agent-skills/index.json with sha256 digests', function () {
    $index = $this->getJson('/.well-known/agent-skills/index.json')->assertOk()->json();

    expect($index['$schema'])->toBe('https://schemas.agentskills.io/discovery/0.2.0/schema.json');

    $byName = collect($index['skills'])->keyBy('name');

    expect($byName->keys()->all())->toContain('acme', 'handbook', 'billing', 'billing-helper');

    foreach ($index['skills'] as $skill) {
        $path = parse_url($skill['url'], PHP_URL_PATH);
        $served = $this->get($path)->assertOk()->getContent();

        expect($skill['type'])->toBe('skill-md')
            ->and($skill['digest'])->toBe('sha256:'.hash('sha256', $served))
            ->and($skill['description'])->not->toBe('');
    }
});

it('leaves private sets out of the index for guests', function () {
    Gate::define('viewLarecipe', fn ($user = null) => $user !== null);
    Sets::withBilling(['access' => ['mode' => 'gate']]);

    $guest = $this->getJson('/.well-known/agent-skills/index.json')->assertOk();

    expect(collect($guest->json('skills'))->pluck('name')->all())->not->toContain('billing')
        ->and($guest->headers->get('Cache-Control'))->toContain('public');

    $member = $this->actingAs(Sets::user())->getJson('/.well-known/agent-skills/index.json')->assertOk();

    expect(collect($member->json('skills'))->pluck('name')->all())->toContain('billing')
        ->and($member->headers->get('Cache-Control'))->toContain('private');

    $this->get('/billing/skill.md')->assertOk();
});

it('reports skill fetches', function () {
    Event::fake([SkillServed::class]);

    $this->get('/billing/skill.md', ['User-Agent' => 'curl/8'])->assertOk();
    $this->get('/.well-known/agent-skills/index.json')->assertOk();

    Event::assertDispatched(SkillServed::class, fn (SkillServed $event) => $event->set === 'billing' && $event->skill === 'billing' && $event->kind === 'agent');
    Event::assertDispatched(SkillServed::class, fn (SkillServed $event) => $event->set === null && $event->file === 'index.json');
});
