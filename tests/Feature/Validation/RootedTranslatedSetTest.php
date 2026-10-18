<?php

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

/*
 * A versioned, translated set mounted at the domain root (route prefix ''),
 * like larecipe.saleem.dev. Validation follows what readers get: a page that
 * is not translated yet falls back to the original, so links and navigation
 * entries that reach it are fine.
 */

beforeEach(function () {
    Sets::use(['rooted' => [
        'route' => ['prefix' => '', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => Sets::fixture('rooted')],
    ]]);
});

/** @return list<array> */
function rootedIssues(string $rule): array
{
    $report = app(ValidateDocsAction::class)->execute('rooted');

    return collect($report->issues)->map(fn (ValidationIssue $issue) => $issue->toArray())->where('rule', $rule)->values()->all();
}

it('lets a translation link to a page that falls back to the original', function () {
    $messages = collect(rootedIssues('links.broken'))->where('page', 'ar/3.x/index')->pluck('message')->all();

    expect($messages)->toBe(['Link to /3.x/nowhere: there is no page at "nowhere".']);
});

it('lets a translation list a page in its navigation that falls back to the original', function () {
    $missing = collect(rootedIssues('navigation.missing-page'))->pluck('message')->all();

    expect($missing)->toBe(['The navigation lists "ghost", but there is no such page.']);
});

it('follows the same fallback when it checks one translated page before a write', function () {
    $page = Page::make(
        new PageRef('rooted', '3.x', 'ar', 'notes'),
        new Frontmatter(['title' => 'ملاحظات', 'description' => 'صفحة جديدة.']),
        "# ملاحظات\n\nاقرأ [الدليل](guide.md#setup) و[الأصل](/2.x/old)، أما [هذه](/2.x/gone) فلا.\n",
    );

    $links = collect(app(ValidatePageAction::class)->execute($page)->issues)->where('rule', 'links.broken')->pluck('message')->all();

    expect($links)->toBe(['Link to /2.x/gone: there is no page at "gone".']);
});

it('checks absolute links on a set mounted at the domain root, in any version', function () {
    $links = collect(rootedIssues('links.broken'))->where('page', '3.x/index')->pluck('level', 'message')->all();

    expect($links)->toBe([
        'Link to /3.x/missing-page: there is no page at "missing-page".' => 'error',
        'Link to /2.x/gone: there is no page at "gone".' => 'error',
    ]);
});
