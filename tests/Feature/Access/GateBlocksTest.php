<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Access\Actions\CheckGateBlocksAction;
use LaRecipe\Domains\Access\Actions\FilterGatedSourceAction;
use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Access\DTOs\GateIssue;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
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
    // Count page renders only; search builds its records with purpose "search".
    LaRecipe::extendMarkdown(function ($environment, $state) use (&$parses) {
        $environment->addEventListener(DocumentParsedEvent::class, function () use (&$parses, $state) {
            if (($state->context['purpose'] ?? null) !== 'search') {
                $parses++;
            }
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

dataset('unreadable gate tags', [
    'inline in a sentence' => ["Plans: <Can ability=\"view-billing\">SECRET</Can> end.\n\nAfter.\n", 1],
    'in a table cell' => ["| Plan | Price |\n| - | - |\n| Pro | <Can ability=\"view-billing\">SECRET</Can> |\n\nAfter.\n", 1],
    'in a heading' => ["## Pricing <Can ability=\"view-billing\">SECRET</Can>\n\nAfter.\n", 1],
    'in a list item' => ["- Pro <Can ability=\"view-billing\">SECRET</Can>\n\nAfter.\n", 1],
    'an opening tag split over lines' => ["<Can\n  ability=\"view-billing\">\nSECRET\n</Can>\n\nAfter.\n", 1],
    'split over lines, with blank lines inside' => ["<Can\n  ability=\"view-billing\">\n\nSECRET\n\n</Can>\n\nAfter.\n", 1],
    'text after the opening tag' => ["<Can ability=\"view-billing\"> SECRET\n</Can>\n\nAfter.\n", 1],
    'a lowercase tag' => ["<can ability=\"view-billing\">\nSECRET\n</can>\n\nAfter.\n", 1],
    'a quote line without >' => ["> <Can ability=\"view-billing\">\nSECRET\n> </Can>\n\nAfter.\n", 1],
]);

it('never shows content around a gate tag it cannot read, on any surface, and fails validation', function (string $body, int $line) {
    $page = Page::make(new PageRef('gated', '2.x', 'en', 'unreadable'), new Frontmatter(['title' => 'Unreadable', 'description' => 'Gate tags the parser cannot read.']), $body);

    $surfaces = [
        'guest' => app(RenderPageAction::class)->execute($page)->html,
        'reader holding the ability' => app(RenderPageAction::class)->execute($page, null, Audience::granting(['view-billing'])->toContext())->html,
        'search' => app(RenderPageAction::class)->execute($page, null, ['purpose' => 'search'])->html,
        'agent markdown' => app(AgentMarkdownRenderer::class)->render($page, ['url' => 'https://acme.test/gated/2.x/unreadable']),
        'source view' => app(FilterGatedSourceAction::class)->execute($page, Audience::guest())->body,
    ];

    foreach ($surfaces as $surface => $output) {
        expect($output)->not->toContain('SECRET', "{$surface} shows the gated text")->toContain('After.');
    }

    $issues = collect(app(ValidatePageAction::class)->execute($page)->issues)->where('rule', 'access.gate-syntax')->values();

    expect($issues)->not->toBeEmpty()
        ->and($issues[0]->level)->toBe('error')
        ->and($issues[0]->line)->toBe($line)
        ->and($issues[0]->fix)->toContain('own line');
})->with('unreadable gate tags');

it('reads a gate tag whose ability holds a >', function () {
    $page = Page::make(new PageRef('gated', '2.x', 'en', 'odd'), new Frontmatter(['title' => 'Odd']), "<Can ability=\"a>b\">\nSECRET\n</Can>\n");
    $rendered = app(RenderPageAction::class)->execute($page);

    expect($rendered->html)->not->toContain('SECRET')
        ->and($rendered->abilities)->toBe(['a>b'])
        ->and($rendered->issues)->toBe([]);
});

it('records every ability a page asks about, nested ones included', function () {
    $rendered = app(RenderPageAction::class)->execute(
        app(GetPageAction::class)->execute(new PageRef('gated', '2.x', 'en', 'index')),
    );

    expect($rendered->abilities)->toEqualCanonicalizing(['view-billing', 'manage-billing']);
});

it('keeps every gate block for a search parse, which filters per audience itself', function () {
    $page = app(GetPageAction::class)->execute(new PageRef('gated', '2.x', 'en', 'index'));
    $search = app(RenderPageAction::class)->execute($page, null, ['purpose' => 'search']);

    expect($search->html)->toContain('EXPORT-SECRET', 'MANAGER-SECRET', 'GUEST-ONLY', 'QUOTE-SECRET')
        ->and($search->abilities)->toEqualCanonicalizing(['view-billing', 'manage-billing']);

    expect(app(RenderPageAction::class)->execute($page, null, ['purpose' => 'html'])->html)->not->toContain('EXPORT-SECRET');
});

it('reports gate blocks with an undefined ability in larecipe:validate, on their line', function () {
    $rules = collect(app(ValidateDocsAction::class)->execute('gated')->issues)->pluck('rule');

    expect($rules)->not->toContain(GateIssue::UNKNOWN_ABILITY);

    $page = Page::make(
        new PageRef('gated', '2.x', 'en', 'exports'),
        new Frontmatter(['title' => 'Exports', 'description' => 'Billing exports.']),
        "# Exports\n\n<Can ability=\"view-biling\">\nExport invoices as CSV.\n</Can>\n\n<Cannot ability=\"launch-rockets\">\nAsk your owner.\n</Cannot>\n",
    );

    $issues = collect(app(ValidatePageAction::class)->execute($page)->issues)
        ->where('rule', GateIssue::UNKNOWN_ABILITY)
        ->map(fn (ValidationIssue $issue) => $issue->toArray())
        ->values();

    expect($issues)->toHaveCount(2)
        ->and($issues[0])->toMatchArray(['level' => 'warning', 'set' => 'gated', 'page' => '2.x/exports', 'line' => 3, 'fix' => 'Use "view-billing", or define "view-biling" with Gate::define() in a service provider.'])
        ->and($issues[1])->toMatchArray(['line' => 7, 'fix' => 'Define "launch-rockets" with Gate::define() in a service provider.']);
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
