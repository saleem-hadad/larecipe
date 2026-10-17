<?php

use LaRecipe\Domains\Export\Services\ExportFiles;
use LaRecipe\Domains\Export\Services\StaticHtml;

it('makes page links static-friendly and leaves everything else alone', function () {
    $static = new StaticHtml(['/docs/install' => true, '/docs' => true]);
    $html = '<html lang="en"><a href="/docs/install#steps">Install</a> <a href="/docs">Home</a> <a href="/docs/missing">Gone</a> <a href="https://example.com/docs/install">Out</a> <img src="/docs/_assets/current/a.png"></html>';

    expect($static->page($html))->toBe('<html data-lr-static lang="en"><a href="/docs/install/#steps">Install</a> <a href="/docs/">Home</a> <a href="/docs/missing">Gone</a> <a href="https://example.com/docs/install">Out</a> <img src="/docs/_assets/current/a.png"></html>');
});

it('prefixes a base path and rewrites absolute URLs to it', function () {
    $static = new StaticHtml(['/docs/install' => true], '/site', 'https://acme.github.io');
    $html = '<link rel="canonical" href="https://acme.github.io/docs/install"><a href="/docs/install">x</a><script type="application/json" id="lr-boot">{"url":"/docs/install","logo":"/logo.svg","external":"//cdn.example.com/a.js"}</script>';

    expect($static->page($html))->toBe('<link rel="canonical" href="https://acme.github.io/site/docs/install"><a href="/site/docs/install/">x</a><script type="application/json" id="lr-boot">{"url":"/site/docs/install/","logo":"/site/logo.svg","external":"//cdn.example.com/a.js"}</script>');
});

it('empties CSRF tokens, since a static host cannot check them', function () {
    expect((new StaticHtml([]))->page('<form><input type="hidden" name="_token" value="abc123"></form>'))
        ->toBe('<form><input type="hidden" name="_token" value=""></form>');
});

it('finds the files a page needs, search indexes included', function () {
    $html = '<link href="/larecipe-assets/abc/app.css"><img src="/docs/_assets/x.png?v=1"><script type="application/json" id="lr-boot">{"search":{"index":"\/docs\/2.x\/_search\/guest.a1b2.json"}}</script>';

    expect(StaticHtml::references($html))->toBe(['/larecipe-assets/abc/app.css', '/docs/_assets/x.png', '/docs/2.x/_search/guest.a1b2.json']);
});

it('maps URLs to files and refuses to write outside the export', function () {
    expect(ExportFiles::fileFor('/docs/install', true))->toBe('docs/install/index.html')
        ->and(ExportFiles::fileFor('/', true))->toBe('index.html')
        ->and(ExportFiles::fileFor('/docs/install.md', false))->toBe('docs/install.md');

    (new ExportFiles(sys_get_temp_dir().'/larecipe-export-guard'))->write('../escape.txt', 'no');
})->throws(InvalidArgumentException::class);
