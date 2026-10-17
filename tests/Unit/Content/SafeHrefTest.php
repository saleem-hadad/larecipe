<?php

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\Support\SafeHref;

it('keeps http, https, mailto and relative links', function (string $href) {
    expect(SafeHref::of($href))->toBe($href);
})->with([
    'https://github.com/acme/acme',
    'http://localhost:8000/docs',
    'mailto:docs@acme.dev',
    '/changelog',
    'guides/billing',
    '../2.x/index',
    '#install',
    '?tab=php',
    'notes/a:b',
]);

it('refuses scripts, data, other schemes and protocol-relative links, however they are disguised', function (string $href) {
    expect(SafeHref::of($href))->toBeNull();
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    ' javascript:alert(1)',
    "java\tscript:alert(1)",
    "java\nscript:alert(1)",
    "\x01javascript:alert(1)",
    'vbscript:msgbox(1)',
    'data:text/html,<script>alert(1)</script>',
    'tel:+15550100',
    '//evil.example/x',
    '/\\evil.example/x',
    '\\\\evil.example/x',
    '',
    '   ',
]);

it('drops refused links from navigation while parsing, and remembers them', function () {
    $tree = NavigationTree::fromJson(['tabs' => [
        ['label' => 'Guides', 'groups' => [['group' => 'Start', 'pages' => ['index', ['label' => 'Bad', 'href' => 'javascript:alert(1)'], ['label' => 'Repo', 'href' => 'https://github.com/acme/acme']]]]],
        ['label' => 'Evil tab', 'href' => '//evil.example'],
    ]]);

    $links = collect($tree->tabs[0]->items[0]->children)->pluck('href')->filter()->values()->all();

    expect($links)->toBe(['https://github.com/acme/acme'])
        ->and($tree->tabs[1]->href)->toBeNull()
        ->and($tree->refusedLinks)->toBe(['javascript:alert(1)', '//evil.example']);
});

it('leaves refused links out of the navbar, the footer and the repository', function () {
    $manifest = Manifest::fromArray([
        'navbar' => ['links' => [['label' => 'JS', 'href' => "javascript:alert('navbar')"], ['label' => 'Changelog', 'href' => '/changelog']], 'cta' => ['label' => 'Cta', 'href' => "javascript:alert('cta')"]],
        'footer' => ['links' => [['label' => 'JS', 'href' => 'data:text/html,x']], 'text' => 'Made with care.'],
        'repository' => ['url' => "javascript:alert('repo')"],
    ]);

    expect($manifest->navbar())->toBe(['links' => [['label' => 'Changelog', 'href' => '/changelog']]])
        ->and($manifest->footer())->toBe(['links' => [], 'text' => 'Made with care.'])
        ->and($manifest->repository()['url'])->toBeNull()
        ->and($manifest->refusedLinks())->toBe(["javascript:alert('navbar')", "javascript:alert('cta')", 'data:text/html,x', "javascript:alert('repo')"]);
});
