<?php

use LaRecipe\Domains\Agents\Laravel\Doctor\WordParity;

it('measures the article words the Markdown twin carries', function () {
    $html = '<html><nav>Sidebar words</nav><article class="lr-article" data-lr-article><h1>Install</h1><p>Require the package first.</p></article></html>';

    expect(WordParity::measure($html, "# Install\n\nRequire the package first."))->toBe(1.0)
        ->and(WordParity::measure($html, "# Install\n\nRequire it."))->toBe(0.5);
});

it('treats every tag as a word break, so adjacent elements never fuse into one word', function () {
    $html = '<article data-lr-article><a class="lr-card"><p class="lr-card-title">Installation</p><p>Requirements</p></a><li><span>1</span><span>Paste</span></li></article>';

    expect(WordParity::measure($html, 'Installation: requirements. Paste it.'))->toBe(1.0);
});

it('ignores scripts and styles, and counts an empty article as whole', function () {
    expect(WordParity::measure('<article data-lr-article><script>secretword()</script><p>Visible text</p></article>', 'Visible text'))->toBe(1.0)
        ->and(WordParity::measure('<article data-lr-article></article>', ''))->toBe(1.0);
});
