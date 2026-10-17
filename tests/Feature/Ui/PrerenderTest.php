<?php

use LaRecipe\Tests\Support\TrackG;
use LaRecipe\Tests\TestCase;

/*
 * The Blade prerender: the complete page for readers without JavaScript,
 * crawlers and agents, drawn with the same regions and class names React
 * renders (ui/src/regions), so the takeover never moves anything.
 */

beforeEach(function () {
    TrackG::useSets(['guide' => [
        'route' => ['prefix' => 'guide', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => TestCase::fixture('track-b')],
        'access' => ['mode' => 'public'],
    ]]);
});

function guideHtml(string $url): string
{
    return (string) test()->get($url)->assertOk()->getContent();
}

it('renders every region of the reading experience', function () {
    $html = guideHtml('/guide/3.x/installation');

    expect($html)
        ->toContain('<header class="lr-header" data-lr-region="Header">')
        ->toContain('<nav class="lr-sidebar" id="lr-sidebar" aria-label="Docs" data-lr-region="Sidebar">')
        ->toContain('<div class="lr-page-header" data-lr-region="PageHeader">')
        ->toContain('<div class="lr-article-host"><article class="lr-article lr-prose" id="lr-article" data-lr-article>')
        ->toContain('<aside class="lr-rail" data-lr-region="Toc">')
        ->toContain('<footer class="lr-footer" data-lr-region="Footer">')
        ->toContain('<p class="lr-breadcrumb lr-label"><span>Get started</span><span class="lr-breadcrumb-sep" aria-hidden="true">/</span><span>Installation</span></p>')
        ->toContain('<h1 class="lr-title">Installation</h1>')
        ->toContain('<a class="lr-toc-link" href="#requirements"  data-active="" aria-current="true" >Requirements</a>')
        ->toContain('Built with LaRecipe');
});

it('marks the controls that need JavaScript, and flags JavaScript before first paint', function () {
    $html = guideHtml('/guide/3.x/installation');

    expect($html)
        ->toContain("d.classList.add('lr-js')")
        ->toContain('class="lr-search-trigger lr-js-only"')
        ->toContain('class="lr-menu lr-theme-menu lr-js-only"')
        ->toMatch('#<li\s+class="lr-js-only"\s*><button type="button" class="lr-action">.*?Copy as Markdown#s')
        ->toContain('<span>View as Markdown</span></a>');
});

it('opens menus and the phone navigation without JavaScript', function () {
    $html = guideHtml('/guide/3.x/installation');

    expect($html)
        ->toContain('<details class="lr-menu lr-version-menu"><summary class="lr-version-button" aria-label="Version 3.x"><span class="lr-ltr">3.x</span>')
        ->toContain('<a class="lr-icon-button lr-menu-button" href="#lr-sidebar"')
        ->toContain('<a class="lr-scrim" href="#" aria-hidden="true" tabindex="-1"></a>');
});

it('draws icons from the same Lucide geometry React uses', function () {
    $html = guideHtml('/guide/3.x/installation');

    expect($html)->toContain('<svg class="lr-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>');
});

it('renders Arabic as a peer: right to left, Arabic strings, code and versions left to right', function () {
    $html = guideHtml('/guide/ar/3.x/installation');

    expect($html)
        ->toContain('<html lang="ar" dir="rtl"')
        ->toContain('ابحث في التوثيق')
        ->toContain('<p class="lr-label" id="lr-actions-label">للوكلاء</p>')
        ->toContain('<span class="lr-ltr">3.x</span>')
        ->toContain('<a class="lr-skip" href="#lr-article">انتقل إلى المحتوى</a>');
});

it('prints the fallback notice in the reader’s language and the original’s', function () {
    $html = guideHtml('/guide/ar/3.x/configuration');

    expect($html)
        ->toContain('<span>هذه الصفحة لم تُترجم بعد، وأنت تقرأ النسخة الأصلية.</span>')
        ->toContain('<span class="lr-fallback-original" lang="en" dir="ltr">This page is not translated yet. You are reading the original.</span>');
});

it('renders insert points from the manifest and honors a page’s opt-out', function () {
    expect(guideHtml('/guide/3.x/installation'))->toContain('<div class="lr-insert" data-lr-insert="article.after"><div data-lr-component="Subscribe"')
        ->and(guideHtml('/guide/3.x/guides/long-page'))->not->toContain('data-lr-insert="article.after"');
});

it('designs the 404 in the set’s language with plain words', function () {
    $response = $this->get('/guide/3.x/nothing-here')->assertNotFound();

    expect((string) $response->getContent())
        ->toContain('<h1 class="lr-not-found-title">Page not found</h1>')
        ->toContain('There is no page at <code>/guide/3.x/nothing-here</code>.')
        ->toContain('Back to LaRecipe');
});
