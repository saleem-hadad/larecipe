<?php

/*
 * The portable spellings of components: GitHub alerts and `:::` directives
 * produce exactly the HTML of the matching tag.
 */

use LaRecipe\Tests\Unit\Markdown\Render;

dataset('alerts', [
    'note' => ['NOTE', 'note', 'Note'],
    'tip' => ['TIP', 'tip', 'Tip'],
    'important' => ['IMPORTANT', 'important', 'Important'],
    'warning' => ['WARNING', 'warning', 'Warning'],
    'caution' => ['CAUTION', 'caution', 'Caution'],
]);

it('renders GitHub alerts as Callouts', function (string $marker, string $type, string $label) {
    $alert = Render::html("> [!{$marker}]\n> Read this first.");
    $tag = Render::html("<Callout type=\"{$type}\">\nRead this first.\n</Callout>");

    expect($alert->html)->toBe($tag->html)
        ->and($alert->html)->toBe("<aside class=\"lr-callout\" data-type=\"{$type}\" role=\"note\"><div class=\"lr-callout-label\">{$label}</div><div class=\"lr-callout-body\"><p>Read this first.</p></div></aside>\n")
        ->and($alert->components)->toBe(['Callout']);
})->with('alerts');

it('keeps alert markers case-insensitive, with an optional title and several paragraphs', function () {
    $html = Render::html("> [!warning] Before you upgrade\n> Back up the database.\n>\n> Then run the migration.")->html;

    expect($html)->toBe('<aside class="lr-callout" data-type="warning" role="note"><div class="lr-callout-label">Before you upgrade</div><div class="lr-callout-body"><p>Back up the database.</p>'."\n".'<p>Then run the migration.</p></div></aside>'."\n");
});

it('leaves ordinary blockquotes alone', function () {
    expect(Render::html('> Just a quote.')->html)->toBe("<blockquote>\n<p>Just a quote.</p>\n</blockquote>\n")
        ->and(Render::html("> [!UNKNOWN]\n> Text")->html)->toStartWith('<blockquote>');
});

it('serializes alerts back to GitHub alerts for agents', function () {
    expect(Render::markdown("> [!TIP]\n> Use the **cache**."))->toBe("> [!TIP]\n> Use the **cache**.");
});

it('renders callout directives like the Callout tag, with the title on the fence line', function () {
    $directive = Render::html(":::warning Heads up\nVerify the signature.\n:::");
    $tag = Render::html("<Callout type=\"warning\" title=\"Heads up\">\nVerify the signature.\n</Callout>");

    expect($directive->html)->toBe($tag->html);
});

it('renders any component as a directive with props in braces', function () {
    $directive = Render::html(":::callout {type=tip title=\"Fast path\"}\nUse the cache.\n:::");
    $tag = Render::html("<Callout type=\"tip\" title=\"Fast path\">\nUse the cache.\n</Callout>");

    expect($directive->html)->toBe($tag->html)
        ->and(Render::html(":::callout{type=tip}\nHi.\n:::")->html)->toContain('data-type="tip"');
});

it('maps kebab-case directive names to components', function () {
    $html = Render::html(":::code-group\n```bash\nls\n```\n:::")->html;

    expect($html)->toStartWith('<div data-lr-component="CodeGroup"');
});

it('nests directives with longer fences', function () {
    $body = <<<'MD'
::::tabs {sync=pm}
:::tab npm
`npm install`
:::
:::tab Yarn
`yarn add`
:::
::::

After the tabs.
MD;

    $tags = <<<'MD'
<Tabs sync="pm">
<Tab label="npm">
`npm install`
</Tab>
<Tab label="Yarn">
`yarn add`
</Tab>
</Tabs>

After the tabs.
MD;

    expect(Render::html($body)->html)->toBe(Render::html($tags)->html);
});

it('nests directives with equal fences, closing the innermost first', function () {
    $body = ":::steps\n:::step Install\nRun it.\n:::\n:::step Configure\nSet it.\n:::\n:::\n\nDone.";
    $html = Render::html($body)->html;

    expect($html)->toBe(
        '<ol class="lr-steps"><li class="lr-step"><div class="lr-step-title">Install</div><div class="lr-step-body"><p>Run it.</p></div></li>'."\n"
        .'<li class="lr-step"><div class="lr-step-title">Configure</div><div class="lr-step-body"><p>Set it.</p></div></li></ol>'."\n"
        ."<p>Done.</p>\n"
    );
});

it('does not close a directive on a fence inside a code block', function () {
    $html = Render::html(":::note\n```text\n:::\n```\nStill inside.\n:::")->html;

    expect($html)->toContain('<p>Still inside.</p></div></aside>');
});

it('keeps raw directive content as text', function () {
    $html = Render::html(":::mermaid\ngraph TD\n  A-->B\n:::")->html;

    expect($html)->toContain('<pre class="lr-mermaid"><code>graph TD'."\n".'  A--&gt;B</code></pre>');
});
