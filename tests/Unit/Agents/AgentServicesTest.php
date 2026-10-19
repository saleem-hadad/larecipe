<?php

use LaRecipe\Domains\Agents\DTOs\IndexEntry;
use LaRecipe\Domains\Agents\DTOs\OutlineSection;
use LaRecipe\Domains\Agents\Services\LlmsTxtWriter;
use LaRecipe\Domains\Agents\Services\MarkdownSection;
use LaRecipe\Domains\Agents\Services\PathResolver;
use LaRecipe\Domains\Agents\Services\ScopeParser;
use LaRecipe\Domains\Ask\Services\CitationGuard;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Validation\Services\LinkPaths;

function versionedSet(string $prefix = 'docs'): DocSet
{
    return new DocSet(
        'docs',
        SetConfig::fromConfig('docs', ['route' => ['prefix' => $prefix]], []),
        Manifest::fromArray(['name' => 'Acme', 'versions' => [['id' => '3.x', 'default' => true], '2.x'], 'locales' => [['code' => 'en', 'default' => true], 'ar']]),
    );
}

it('resolves whatever path an agent passes to the page it means', function (string $input, ?string $version, string $expected, ?string $anchor) {
    $resolved = (new PathResolver)->resolve(versionedSet(), $input, $version, null, '3.x', 'en');

    expect($resolved['ref']->version.'|'.$resolved['ref']->locale.'|'.$resolved['ref']->path)->toBe($expected)
        ->and($resolved['anchor'])->toBe($anchor);
})->with([
    'a path' => ['guides/webhooks', null, '3.x|en|guides/webhooks', null],
    'a .md URL' => ['https://acme.dev/docs/2.x/guides/webhooks.md', null, '2.x|en|guides/webhooks', null],
    'a page URL with an anchor' => ['/docs/ar/2.x/billing#retries', null, '2.x|ar|billing', 'retries'],
    'an explicit version wins' => ['/docs/2.x/billing', '3.x', '3.x|en|billing', null],
    'a folder index' => ['guides/index.md', null, '3.x|en|guides', null],
    'the landing page' => ['/docs', null, '3.x|en|index', null],
    'a page under a folder named like the prefix' => ['docs/intro', null, '3.x|en|docs/intro', null],
]);

it('cuts one section out of a page, keeping its header', function () {
    $markdown = "---\ntitle: Webhooks\n---\n\n# Webhooks\n\nIntro.\n\n## Retries\n\nRetried.\n\n```php\n# not a heading\n```\n\n### Backoff\n\nExponential.\n\n## Signing {#sign}\n\nHMAC.\n";
    $sections = new MarkdownSection;

    expect($sections->extract($markdown, 'Retries'))->toBe("---\ntitle: Webhooks\n---\n\n## Retries\n\nRetried.\n\n```php\n# not a heading\n```\n\n### Backoff\n\nExponential.\n")
        ->and($sections->extract($markdown, '#sign'))->toContain("## Signing {#sign}\n\nHMAC.")
        ->and($sections->extract($markdown, 'backoff'))->toContain('Exponential.')->not->toContain('Retried.')
        ->and($sections->extract($markdown, 'nope'))->toBeNull()
        ->and(array_column($sections->headings($markdown), 'text'))->toBe(['Webhooks', 'Retries', 'Backoff', 'Signing']);
});

it('writes the llms.txt format', function () {
    $text = (new LlmsTxtWriter)->write("Acme\nDocs", "Billing for\nLaravel.", ['Details.', ''], [
        new OutlineSection('Guides', 'guides', [new IndexEntry('Web [hooks]', 'https://acme.dev/docs/a b.md', "Signed\nevents.")], [
            new OutlineSection('Advanced', 'advanced', [new IndexEntry('Retries', 'https://acme.dev/docs/retries.md')]),
        ]),
        new OutlineSection('Empty', null, []),
    ]);

    expect($text)->toBe("# Acme Docs\n\n> Billing for Laravel.\n\nDetails.\n\n## Guides\n\n- [Web \\[hooks\\]](https://acme.dev/docs/a%20b.md): Signed events.\n\n## Guides / Advanced\n\n- [Retries](https://acme.dev/docs/retries.md)\n");
});

it('reads the locale, version and group in front of an agent file', function () {
    $parser = new ScopeParser;

    expect($parser->parse(versionedSet(), 'ar/2.x/billing/advanced'))->toBe(['locale' => 'ar', 'version' => '2.x', 'rest' => ['billing', 'advanced']])
        ->and($parser->parse(versionedSet(), null))->toBe(['locale' => 'en', 'version' => null, 'rest' => []])
        ->and($parser->parse(versionedSet(), 'billing'))->toBe(['locale' => 'en', 'version' => null, 'rest' => ['billing']]);
});

it('only counts links to known pages as citations', function () {
    $guard = new CitationGuard([
        '/docs/installation' => ['url' => 'https://acme.dev/docs/installation', 'title' => 'Installation'],
        '/docs' => ['url' => 'https://acme.dev/docs', 'title' => 'Introduction'],
    ]);

    $citations = $guard->citations('See [install](https://acme.dev/docs/installation.md#env), [home](/docs/index.md), https://acme.dev/docs/installation and [fake](https://acme.dev/docs/secret).');

    expect($citations)->toBe([
        ['url' => 'https://acme.dev/docs/installation', 'title' => 'Installation'],
        ['url' => 'https://acme.dev/docs', 'title' => 'Introduction'],
    ]);
});

it('resolves links the way the delivery link resolver does', function () {
    $paths = new LinkPaths;

    expect($paths->relative('guides/webhooks', false, '../installation.md#env'))->toBe(['path' => 'installation', 'anchor' => 'env'])
        ->and($paths->relative('guides', true, 'webhooks.md'))->toBe(['path' => 'guides/webhooks', 'anchor' => null])
        ->and($paths->relative('index', false, '/billing/index.md'))->toBe(['path' => 'billing', 'anchor' => null])
        ->and($paths->relative('index', false, 'https://example.com/a.md'))->toBeNull()
        ->and($paths->absolute('docs', '3.x', '/docs/3.x/billing#retries'))->toBe(['path' => 'billing', 'anchor' => 'retries'])
        ->and($paths->absolute('docs', '3.x', '/docs/3.x'))->toBe(['path' => 'index', 'anchor' => null])
        ->and($paths->absolute('docs', '3.x', '/docs/2.x/billing'))->toBeNull()
        ->and($paths->absolute('', '', '/billing'))->toBe(['path' => 'billing', 'anchor' => null])
        ->and($paths->absolute('docs', '', '/docs/llms.txt'))->toBeNull();
});
