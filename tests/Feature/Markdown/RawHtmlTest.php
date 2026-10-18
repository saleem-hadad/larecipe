<?php

/*
 * Raw HTML in Markdown (ARCHITECTURE.md 6.2 and 22): escaped by default, passed
 * through for a repository set that opts into html_input: allow, and never on
 * a database set, whose rendered pages also go through an HTML sanitizer.
 */

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\TestCase;

function useHtmlSets(array $repository = [], array $database = []): void
{
    Sets::use([
        'docs' => array_replace(['route' => ['prefix' => 'docs'], 'source' => ['driver' => 'repository', 'path' => TestCase::fixture('docs')]], $repository),
        'kb' => array_replace(['route' => ['prefix' => 'kb'], 'source' => ['driver' => 'database']], $database),
    ]);
}

function renderIn(string $set, string $body): string
{
    return app(RenderPageAction::class)->execute(Page::make(new PageRef($set, 'current', 'en', 'page'), new Frontmatter(['title' => 'Page']), $body))->html;
}

it('escapes raw HTML by default', function () {
    useHtmlSets();

    expect(renderIn('docs', "<div class=\"note\">Raw</div>\n\nText with <kbd>K</kbd>.\n"))
        ->toContain('&lt;div class="note"&gt;Raw&lt;/div&gt;')
        ->toContain('&lt;kbd&gt;K&lt;/kbd&gt;');
});

it('passes raw HTML through for a repository set that opts in', function () {
    useHtmlSets(['html_input' => 'allow']);

    expect(renderIn('docs', "<div class=\"note\">Raw</div>\n\nText with <kbd>K</kbd>.\n\n<script>alert(1)</script>\n"))
        ->toContain('<div class="note">Raw</div>')
        ->toContain('<kbd>K</kbd>')
        // GitHub's tag filter still applies: no scripts, frames or styles.
        ->toContain('&lt;script>alert(1)&lt;/script>');
});

it('never passes raw HTML through on a database set, even when asked to', function () {
    useHtmlSets(database: ['html_input' => 'allow']);

    expect(renderIn('kb', "<div class=\"note\">Raw</div>\n"))->toContain('&lt;div')->toContain('Raw')->not->toContain('<div');
});

it('sanitizes a database set page, keeping LaRecipe markup and dropping scripts, handlers and javascript: links', function () {
    useHtmlSets();
    app(LaRecipe::class)->component('Unsafe')->fallback(fn () => '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><img src="/a.png" onerror="alert(2)" alt="">');

    $body = "## Setup\n\n<Unsafe />\n\n<Tabs>\n<Tab label=\"npm\">\n`npm i`\n</Tab>\n</Tabs>\n\n```php\necho 1;\n```\n";
    $html = renderIn('kb', $body);

    expect($html)->not->toContain('onclick')->not->toContain('<script')->not->toContain('javascript:')->not->toContain('onerror')
        ->toContain('<h2 id="setup">Setup</h2>')
        ->toContain('data-lr-component="Tabs"')
        ->toContain('class="lr-tabs"')
        ->toContain('class="lr-code" data-language="php"')
        ->toContain('data-line="1"')
        ->toContain('style="color:');

    expect(renderIn('docs', $body))->toContain('onclick="steal()"');
});
