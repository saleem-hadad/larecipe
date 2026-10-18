<?php

use Illuminate\Support\Collection;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Support\Freshness;
use LaRecipe\Tests\Support\TrackG;
use LaRecipe\Tests\TestCase;

/*
 * The boot payload the reading UI renders from: every word a reader sees is
 * worded here once, in the reader's locale, so the prerender and React agree.
 */

beforeEach(function () {
    TrackG::useSets(['guide' => [
        'route' => ['prefix' => 'guide', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => TestCase::fixture('track-b')],
        'access' => ['mode' => 'public'],
    ]]);
});

function guidePayload(string $url): array
{
    return test()->get($url, ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->json();
}

function guideBoot(string $url): array
{
    $html = (string) test()->get($url)->assertOk()->getContent();
    preg_match('#<script type="application/json" id="lr-boot">(.*?)</script>#s', $html, $match);

    return json_decode($match[1] ?? '{}', true);
}

it('delivers the UI strings in the reader’s locale, falling back to English per key', function () {
    $english = guideBoot('/guide/3.x/installation')['strings'];
    $arabic = guideBoot('/guide/ar/3.x/installation')['strings'];

    expect($english['search.label'])->toBe('Search the docs')
        ->and($english['ticket.label'])->toBe('Last change')
        ->and($arabic['search.label'])->toBe('ابحث في التوثيق')
        ->and($arabic['page.on_this_page'])->toBe('في هذه الصفحة')
        ->and(array_keys($arabic))->toEqual(array_keys($english));
});

it('words the page header once: breadcrumb, relative time and reading time', function () {
    $page = guideBoot('/guide/3.x/installation')['page'];

    expect($page['breadcrumb'])->toBe(['Get started'])
        ->and($page['updated_human'])->toEndWith('ago')
        ->and($page['reading_minutes'])->toBeGreaterThanOrEqual(1)
        ->and($page['reading_label'])->toMatch('/^\d+ min read$/');

    expect(guideBoot('/guide/ar/3.x/installation')['page']['reading_label'])->toStartWith('قراءة');
});

function guidePages(string $url): Collection
{
    return collect(guideBoot($url)['navigation'][0]['items'])
        ->flatMap(fn (array $item) => $item['type'] === 'group' ? $item['items'] : [$item])
        ->where('type', 'page');
}

it('marks the other navigation pages changed within larecipe.ui.fresh_days as fresh, with the date the browser keys seen dots on', function () {
    // A window wide enough that every fixture file counts, whenever it was checked out.
    config(['larecipe.ui.fresh_days' => 3650]);

    $pages = guidePages('/guide/3.x/installation');
    $others = $pages->where('active', false);

    expect($others->pluck('fresh')->unique()->values()->all())->toBe([true])
        ->and($others->every(fn (array $page) => strtotime($page['updated_at']) !== false))->toBeTrue()
        // The page being read is the one the reader has already opened.
        ->and($pages->firstWhere('active', true))->toMatchArray(['path' => 'installation', 'fresh' => false])
        ->and($pages->firstWhere('active', true))->not->toHaveKey('updated_at');
});

it('turns the fresh dots off at fresh_days 0, and dates outside the window are not fresh', function () {
    config(['larecipe.ui.fresh_days' => 0]);
    expect(guidePages('/guide/3.x/installation')->pluck('fresh')->unique()->values()->all())->toBe([false]);

    config(['larecipe.ui.fresh_days' => 1]);
    $freshness = app(Freshness::class);
    expect($freshness->isFresh(new DateTimeImmutable('-23 hours')))->toBeTrue()
        ->and($freshness->isFresh(new DateTimeImmutable('-25 hours')))->toBeFalse()
        ->and($freshness->isFresh(null))->toBeFalse();

    config(['larecipe.ui.fresh_days' => 7]);
    expect($freshness->isFresh(new DateTimeImmutable('-6 days')))->toBeTrue();
});

it('offers six agent destinations at most, with the MCP command for this set', function () {
    $actions = guideBoot('/guide/3.x/installation')['page']['actions'];

    expect($actions['claude'])->toStartWith('https://claude.ai/new?q=Read%20http%3A%2F%2Flocalhost%2Fguide%2F3.x%2Finstallation.md')
        ->and($actions['chatgpt'])->toStartWith('https://chatgpt.com/?q=')
        ->and($actions['cursor'])->toStartWith('https://cursor.com/link/prompt?text=')
        ->and($actions['mcp_command'])->toBe('claude mcp add --transport http larecipe http://localhost/guide/mcp');
});

it('draws no ticket for a page that only has a file date', function () {
    expect(guideBoot('/guide/3.x/installation')['page']['ticket'])->toBeNull();
});

it('words the Last change ticket from the provenance a source recorded', function () {
    // Provenance from a source, enriched by another domain through the presenter.
    app()->afterResolving(PagePresenter::class, function (PagePresenter $presenter) {
        $presenter->extend(fn (DocSet $set, array $payload) => $set->key === 'guide' ? ['page' => ['provenance' => [
            'actor' => 'agent', 'name' => 'Claude Code', 'ref' => '9f3a2c1d0e', 'at' => now()->subHours(2)->format(DATE_ATOM),
            'approved_by' => '@saleem', 'ticket' => '0142', 'pages' => 2, 'additions' => 14, 'deletions' => 3,
        ]]] : []);
    });
    app()->forgetScopedInstances();

    $ticket = guideBoot('/guide/3.x/installation')['page']['ticket'];

    expect($ticket)->toMatchArray([
        'number' => '0142',
        'ref' => '9f3a2c1',
        'summary' => 'Claude Code updated this page.',
        'approved_by' => '@saleem',
        'pages_label' => '2 pages',
        'additions' => 14,
        'deletions' => 3,
        'url' => 'https://github.com/saleem-hadad/larecipe/commit/9f3a2c1d0e',
    ])->and($ticket['time'])->toMatch('/^\d{2}:\d{2}$|^\d{1,2} \w+$/');
});

it('keeps the reader in their language on a fallback page, with the notice in both languages', function () {
    $page = guideBoot('/guide/ar/3.x/configuration')['page'];

    expect($page['fallback_from_locale'])->toBe('ar')
        ->and($page['fallback_notice']['text'])->toBe('هذه الصفحة لم تُترجم بعد، وأنت تقرأ النسخة الأصلية.')
        ->and($page['fallback_notice']['original'])->toBe('This page is not translated yet. You are reading the original.')
        ->and($page['fallback_notice']['original_locale'])->toBe('en');
});

it('sends insert fallbacks with client navigation, honoring a page’s opt-out', function () {
    $installation = guidePayload('/guide/3.x/installation');
    $optedOut = guidePayload('/guide/3.x/guides/long-page');

    // Subscribe has no PHP definition in the package tests: a bare placeholder for React.
    expect($installation['inserts_html']['article.after'])->toBe('<div data-lr-component="Subscribe" data-lr-props=\'[]\'></div>')
        ->and($installation['inserts_html']['footer'])->toBe('')
        ->and($optedOut['inserts_html']['article.after'])->toBe('');
});
