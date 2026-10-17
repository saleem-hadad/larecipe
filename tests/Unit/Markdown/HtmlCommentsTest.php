<?php

/*
 * HTML comments are notes for authors. They never reach the page or the .md
 * twin, while other raw HTML stays escaped.
 */

use LaRecipe\Tests\Unit\Markdown\Render;

it('drops HTML comments from the page and the agent Markdown', function () {
    $body = "# Example\n\n<!-- TODO: add a screenshot -->\n\nFirst paragraph.\n\n<!--\nA note that spans\nseveral lines.\n-->\n\nText <!-- inline note --> after.\n";
    $html = Render::html($body)->html;
    $markdown = Render::markdown($body);

    expect($html)->not->toContain('TODO')->not->toContain('note')->not->toContain('&lt;!--')
        ->toContain('<p>First paragraph.</p>')
        ->toMatch('#<p>Text +after\.</p>#');

    expect($markdown)->not->toContain('TODO')->not->toContain('note')->not->toContain('<!--')
        ->toContain('First paragraph.')
        ->toMatch('/Text +after\./');
});

it('keeps other raw HTML escaped, and comments inside code as written', function () {
    $body = "<div class=\"note\">Raw</div>\n\nUse `<!-- like this -->` to leave a note.\n\n```html\n<!-- kept -->\n```\n";
    $html = Render::html($body)->html;

    expect($html)->toContain('&lt;div class="note"&gt;Raw&lt;/div&gt;')
        ->toContain('<code>&lt;!-- like this --&gt;</code>')
        ->toContain('kept');

    expect(Render::markdown($body))->toContain('`<!-- like this -->`')->toContain("```html\n<!-- kept -->\n```");
});
