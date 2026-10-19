<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Support\DocsLinkResolver;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;
use LaRecipe\Tests\TestCase;

/*
 * The links authors write: relative .md files, and root links ("/x.md") that
 * resolve from the version root. A root link that spells out a page's URL
 * (its version, and the set prefix or locale when there is one) reaches that
 * page too, so "/3.x/index.md" is the 3.x landing page, not a page named "3.x".
 */

$resolver = function (string $prefix, bool $twins) {
    TrackG::useSets(['guide' => [
        'route' => ['prefix' => $prefix, 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => TestCase::fixture('track-b')],
        'access' => ['mode' => 'public'],
    ]]);

    return new DocsLinkResolver(app(SetRegistry::class)->get('guide'), app(DocsUrls::class), app(SourceManager::class)->for('guide'), $twins);
};

$from = fn (string $path = 'guides/webhooks', string $locale = 'en', string $version = '3.x') => new PageRef('guide', $version, $locale, $path);

it('links a version\'s landing page on a root-mounted set', function () use ($resolver, $from) {
    $html = $resolver('', false);
    $twin = $resolver('', true);

    expect($html->page($from(), '/3.x/index.md'))->toBe('/3.x')
        ->and($twin->page($from(), '/3.x/index.md'))->toBe('/3.x/index.md')
        ->and($twin->page($from(), '/2.x/index.md'))->toBe('/2.x/index.md')
        ->and($twin->page($from(), '/3.x/installation.md#requirements'))->toBe('/3.x/installation.md#requirements')
        ->and($twin->page($from(), '../index.md'))->toBe('/3.x/index.md')
        ->and($twin->page($from(), '/index.md'))->toBe('/3.x/index.md');
});

it('links a version\'s landing page under a prefix', function () use ($resolver, $from) {
    $twin = $resolver('guide', true);

    expect($twin->page($from(), '/guide/3.x/index.md'))->toBe('/guide/3.x/index.md')
        ->and($twin->page($from(), '/3.x/index.md'))->toBe('/guide/3.x/index.md')
        ->and($resolver('guide', false)->page($from(), '/guide/2.x/index.md'))->toBe('/guide/2.x');
});

it('links a locale\'s landing page', function () use ($resolver, $from) {
    $twin = $resolver('', true);

    expect($twin->page($from('index', 'ar'), '/ar/3.x/index.md'))->toBe('/ar/3.x/index.md')
        ->and($twin->page($from('index', 'ar'), '/ar/3.x/installation.md'))->toBe('/ar/3.x/installation.md')
        ->and($twin->page($from('index', 'ar'), 'installation.md'))->toBe('/ar/3.x/installation.md')
        ->and($resolver('', false)->page($from('index', 'ar'), '/ar/3.x/index.md'))->toBe('/ar/3.x');
});

it('keeps version-root links that do not name a version', function () use ($resolver, $from) {
    $twin = $resolver('', true);

    expect($twin->page($from(), '/guides/authoring.md'))->toBe('/3.x/guides/authoring.md')
        ->and($twin->page($from('index', 'en', '2.x'), '/guides/authoring.md'))->toBe('/2.x/guides/authoring.md');
});

it('validates a root link that spells out a page URL like the renderer resolves it', function () {
    $root = Fixtures::temporaryDirectory('root-links');
    mkdir($root.'/3.x', 0755, true);
    file_put_contents($root.'/larecipe.json', json_encode(['name' => 'Root', 'versions' => [['id' => '3.x', 'default' => true]]]));
    file_put_contents($root.'/3.x/index.md', "---\ntitle: Home\ndescription: Home.\n---\n\n# Home\n\n## Start\n");
    file_put_contents($root.'/3.x/guide.md', "---\ntitle: Guide\ndescription: Guide.\n---\n\n# Guide\n\n[Home](/3.x/index.md), [start](/3.x/index.md#start) and [nowhere](/3.x/nowhere.md).\n");

    TrackG::useSets(['root' => [
        'route' => ['prefix' => '', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    $broken = collect(app(ValidateDocsAction::class)->execute('root')->issues)
        ->filter(fn (ValidationIssue $issue) => $issue->rule === 'links.broken')
        ->map(fn (ValidationIssue $issue) => $issue->message)
        ->values()->all();

    expect($broken)->toBe(['Link to /3.x/nowhere.md: there is no page at "nowhere".']);
});
