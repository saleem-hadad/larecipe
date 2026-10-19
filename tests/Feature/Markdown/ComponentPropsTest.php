<?php

/*
 * Component props reach the HTML and the islands only when a component
 * declares them: always for built-ins, and for every component on a database
 * set, whose authors may be less trusted than the codebase. A host component
 * on a repository set keeps accepting data LaRecipe does not describe.
 */

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\TestCase;

beforeEach(function () {
    Sets::use([
        'docs' => ['route' => ['prefix' => 'docs'], 'source' => ['driver' => 'repository', 'path' => TestCase::fixture('docs')]],
        'kb' => ['route' => ['prefix' => 'kb'], 'source' => ['driver' => 'database']],
    ]);
});

/** @return list<array<string, mixed>> the data-lr-props of every island on the page */
function islandProps(string $set, string $body): array
{
    $html = app(RenderPageAction::class)->execute(Page::make(new PageRef($set, 'current', 'en', 'page'), new Frontmatter(['title' => 'Page']), $body))->html;
    preg_match_all('/data-lr-props=(?:\'([^\']*)\'|"([^"]*)")/', $html, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match) => json_decode(html_entity_decode(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''), ENT_QUOTES), true), $matches);
}

it('drops props a built-in component does not declare', function () {
    $props = islandProps('docs', "<Tabs sync=\"pm\" onclick=\"steal()\" class=\"evil\">\n<Tab label=\"npm\">\nx\n</Tab>\n</Tabs>\n\n<Mermaid source=\"graph TD; Evil\">\ngraph TD\n  A-->B\n</Mermaid>\n");

    expect($props[0])->toBe(['sync' => 'pm', 'labels' => ['npm']])
        ->and($props[1])->toBe(['source' => "graph TD\n  A-->B"]);
});

it('derives a Video embed only from a YouTube or Vimeo src, never from the author', function () {
    $file = islandProps('docs', '<Video src="https://cdn.example.com/demo.mp4" embed="https://evil.example/player" provider="youtube" />')[0];
    $youtube = islandProps('docs', '<Video src="https://youtu.be/dQw4w9WgXcQ" embed="https://evil.example/player" />')[0];

    expect($file)->not->toHaveKey('embed')->not->toHaveKey('provider')
        ->and($youtube['embed'])->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and($youtube['provider'])->toBe('youtube');
});

it('derives a Loom embed from a share link', function () {
    $loom = islandProps('docs', '<Video src="https://www.loom.com/share/0a1b2c3d4e5f" />')[0];
    $other = islandProps('docs', '<Video src="https://www.loom.com/looms/videos" />')[0];

    expect($loom['embed'])->toBe('https://www.loom.com/embed/0a1b2c3d4e5f')
        ->and($loom['provider'])->toBe('loom')
        ->and($other)->not->toHaveKey('embed');
});

it('labels the no-JavaScript link with the video host, in the page language', function () {
    $html = app(RenderPageAction::class)->execute(Page::make(new PageRef('docs', 'current', 'ar', 'video'), new Frontmatter(['title' => 'Video']), '<Video src="https://www.loom.com/share/0a1b2c3d4e5f" />'))->html;

    expect($html)->toContain('شاهد على Loom');
});

it('keeps undeclared props for host components on a repository set, and drops them on a database set', function () {
    app(LaRecipe::class)->component('Chart')->props(['title' => 'string'])->island()->fallback(fn () => '<div class="chart"></div>');

    expect(islandProps('docs', '<Chart title="Sales" series="[1,2,3]" />')[0])->toBe(['title' => 'Sales', 'series' => '[1,2,3]'])
        ->and(islandProps('kb', '<Chart title="Sales" series="[1,2,3]" />')[0])->toBe(['title' => 'Sales']);
});

function renderedHtml(string $body): string
{
    return app(RenderPageAction::class)->execute(Page::make(new PageRef('docs', 'current', 'en', 'page'), new Frontmatter(['title' => 'Page']), $body))->html;
}

it('draws a card icon from the content set, and nothing for an unknown name', function () {
    expect(renderedHtml('<Card title="Ship" icon="rocket">Go.</Card>'))->toContain('<span class="lr-card-icon"><svg class="lr-icon"')
        ->and(renderedHtml('<Card title="Read" icon="BookOpen">Go.</Card>'))->toContain('<span class="lr-card-icon">')
        ->and(renderedHtml('<Card title="Odd" icon="not-an-icon">Go.</Card>'))->not->toContain('lr-card-icon');
});

it('wraps Markdown tables in a sheet that scrolls a wide table sideways', function () {
    expect(renderedHtml("| A | B |\n| - | - |\n| 1 | 2 |"))->toContain('<div class="lr-table"><table>');
});
