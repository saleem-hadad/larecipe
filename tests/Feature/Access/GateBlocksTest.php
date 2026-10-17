<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Access\Actions\CheckGateBlocksAction;
use LaRecipe\Domains\Access\DTOs\GateIssue;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Facades\LaRecipe;
use LaRecipe\Tests\Support\TrackG;
use League\CommonMark\Event\DocumentParsedEvent;

function reader(string $plan): User
{
    return (new User)->forceFill(['id' => crc32($plan), 'name' => ucfirst($plan), 'plan' => $plan]);
}

beforeEach(function () {
    TrackG::useSets(['gated' => TrackG::set('gated', 'gated')]);

    Gate::define('view-billing', fn ($user) => in_array($user->plan, ['enterprise', 'manager'], true));
    Gate::define('manage-billing', fn ($user) => $user->plan === 'manager');
});

/** Every representation of a page, as one reader sees it. */
function representations(string $url): array
{
    $test = test();

    return [
        'html' => $test->get($url)->assertOk()->getContent(),
        'json' => $test->get($url, ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->json('page.html'),
        'markdown' => $test->get($url.'.md')->assertOk()->getContent(),
    ];
}

it('removes every gated block a guest may not see, in HTML, JSON and Markdown alike', function () {
    foreach (representations('/gated/2.x/index') as $format => $content) {
        expect($content)
            ->toContain('PUBLIC-TEXT', 'GUEST-ONLY', 'CLOSING-TEXT', 'CODE-SAMPLE')
            ->not->toContain('EXPORT-SECRET', "{$format} leaked a <Can> block to a guest")
            ->not->toContain('MANAGER-SECRET')
            ->not->toContain('QUOTE-SECRET');
    }
});

it('shows a reader exactly the blocks their abilities allow', function () {
    $this->actingAs(reader('enterprise'));

    foreach (representations('/gated/2.x/index') as $content) {
        expect($content)
            ->toContain('PUBLIC-TEXT', 'EXPORT-SECRET', 'QUOTE-SECRET', 'CODE-SAMPLE')
            ->not->toContain('MANAGER-SECRET')
            ->not->toContain('GUEST-ONLY');
    }

    $this->actingAs(reader('manager'));

    expect($this->get('/gated/2.x/index.md')->getContent())->toContain('EXPORT-SECRET', 'MANAGER-SECRET')->not->toContain('GUEST-ONLY');
});

it('leaves no trace of a removed block: no heading, no TOC entry, no tags', function () {
    $guest = $this->get('/gated/2.x', ['Accept' => 'application/vnd.larecipe.page+json'])->json('page');

    expect(collect($guest['toc'])->pluck('id')->all())->toBe(['samples'])
        ->and($guest['html'])->not->toContain('id="exports"')->not->toContain('<p>&lt;Can')->not->toContain('<p>&lt;/Can')->not->toContain('<blockquote>');

    $markdown = $this->get('/gated/2.x/index.md')->getContent();

    // The only tags left are the ones inside the code sample.
    expect($markdown)->not->toContain('## Exports')
        ->and(substr_count($markdown, '<Can'))->toBe(1)
        ->and(substr_count($markdown, '</Can>'))->toBe(1)
        ->and($markdown)->not->toContain('Cannot');

    $this->actingAs(reader('enterprise'));

    expect(collect($this->get('/gated/2.x', ['Accept' => 'application/vnd.larecipe.page+json'])->json('page.toc'))->pluck('id')->all())
        ->toBe(['exports', 'samples']);
});

it('keeps tags inside code samples as code', function () {
    expect($this->get('/gated/2.x/index.md')->getContent())->toContain("```markdown\n<Can ability=\"view-billing\">\nCODE-SAMPLE");
});

it('caches one render per audience variant, never per user', function () {
    $parses = 0;
    LaRecipe::extendMarkdown(function ($environment) use (&$parses) {
        $environment->addEventListener(DocumentParsedEvent::class, function () use (&$parses) {
            $parses++;
        });
    });

    $this->get('/gated/2.x/plans')->assertOk();
    $this->get('/gated/2.x/plans')->assertOk();
    expect($parses)->toBe(1);

    $this->get('/gated/2.x/index')->assertOk();
    expect($parses)->toBe(2);

    $this->actingAs(reader('enterprise'))->get('/gated/2.x/index')->assertOk();
    expect($parses)->toBe(3);

    $this->actingAs((new User)->forceFill(['id' => 99, 'plan' => 'enterprise']))->get('/gated/2.x/index')->assertOk();
    expect($parses)->toBe(3);

    $this->actingAs(reader('free'))->get('/gated/2.x/index')->assertSee('GUEST-ONLY');
    expect($parses)->toBe(3);

    $this->actingAs(reader('manager'))->get('/gated/2.x/index')->assertSee('MANAGER-SECRET');
    expect($parses)->toBe(4);
});

it('never lets a shared cache keep a variant meant for one reader', function () {
    $guest = $this->get('/gated/2.x/index.md');
    expect($guest->headers->get('Cache-Control'))->toContain('public')
        ->and($guest->headers->get('Vary'))->toContain('Cookie');

    $this->actingAs(reader('enterprise'));

    foreach (['/gated/2.x/index', '/gated/2.x/index.md'] as $url) {
        $response = $this->get($url, ['If-None-Match' => $guest->headers->get('ETag') ?? '"x"']);

        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
            ->and($response->headers->get('ETag'))->toBeNull();
    }
});

it('treats a page without gate blocks exactly as before', function () {
    $response = $this->get('/gated/2.x/plans.md')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Vary'))->toBe('Accept')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('hides a block that names no ability from everyone and reports it', function () {
    $page = Page::make(
        new PageRef('gated', '2.x', 'en', 'broken'),
        new Frontmatter([]),
        "# Broken\n\n<Can>\nNO-ABILITY\n</Can>\n\n<Cannot>\nALSO-HIDDEN\n</Cannot>\n",
    );

    $rendered = app(RenderPageAction::class)->execute($page);

    expect($rendered->html)->not->toContain('NO-ABILITY')->not->toContain('ALSO-HIDDEN')
        ->and(collect($rendered->issues)->pluck('component')->all())->toBe(['Can', 'Cannot']);
});

it('records every ability a page asks about, nested ones included', function () {
    $rendered = app(RenderPageAction::class)->execute(
        app(GetPageAction::class)->execute(new PageRef('gated', '2.x', 'en', 'index')),
    );

    expect($rendered->abilities)->toEqualCanonicalizing(['view-billing', 'manage-billing']);
});

it('warns about abilities the host never defined, with the closest one as the fix', function () {
    $issues = app(CheckGateBlocksAction::class)->execute(['view-biling', 'view-billing', 'launch-rockets']);

    expect($issues)->toHaveCount(2)
        ->and($issues[0]->rule)->toBe(GateIssue::UNKNOWN_ABILITY)
        ->and($issues[0]->level)->toBe('warning')
        ->and($issues[0]->fix)->toContain('"view-billing"')
        ->and($issues[1]->ability)->toBe('launch-rockets')
        ->and($issues[1]->fix)->toContain('Gate::define()');

    expect(collect(app(CheckGateBlocksAction::class)->execute(['view-billing'], pageIsPrivate: true))->pluck('rule')->all())
        ->toBe([GateIssue::REDUNDANT_GATE]);
});
