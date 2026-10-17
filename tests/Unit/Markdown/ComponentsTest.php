<?php

/*
 * Renderer snapshots for every built-in component: the exact server HTML the
 * UI styles (Components/COMPONENTS.md) and the agent Markdown form.
 */

use LaRecipe\Tests\Unit\Markdown\Render;

it('renders a Callout as an aside and a GitHub alert', function () {
    $body = "<Callout type=\"warning\" title=\"Heads up\">\nVerify the **signature**.\n</Callout>";

    expect(Render::html($body)->html)->toBe(
        '<aside class="lr-callout" data-type="warning" role="note"><div class="lr-callout-label">Heads up</div><div class="lr-callout-body"><p>Verify the <strong>signature</strong>.</p></div></aside>'."\n"
    )->and(Render::markdown($body))->toBe("> [!WARNING]\n> **Heads up**\n> Verify the **signature**.");
});

it('renders Steps as an ordered list and a numbered Markdown list', function () {
    $body = <<<'MD'
<Steps>
<Step title="Install">
Run the installer.
</Step>
<Step title="Configure">
Set the key.
</Step>
</Steps>
MD;

    expect(Render::html($body)->html)->toBe(
        '<ol class="lr-steps"><li class="lr-step"><div class="lr-step-title">Install</div><div class="lr-step-body"><p>Run the installer.</p></div></li>'."\n"
        .'<li class="lr-step"><div class="lr-step-title">Configure</div><div class="lr-step-body"><p>Set the key.</p></div></li></ol>'."\n"
    )->and(Render::markdown($body))->toBe("1. **Install**\n\n   Run the installer.\n\n2. **Configure**\n\n   Set the key.");
});

it('renders Tabs as an island whose fallback shows every panel', function () {
    $body = <<<'MD'
<Tabs default="Yarn" sync="pm">
<Tab label="npm">
`npm install`
</Tab>
<Tab label="Yarn">
`yarn add`
</Tab>
</Tabs>
MD;

    expect(Render::html($body)->html)->toBe(
        '<div data-lr-component="Tabs" data-lr-props=\'{"default":"Yarn","sync":"pm","labels":["npm","Yarn"]}\'><div class="lr-tabs">'
        .'<section class="lr-tab" data-label="npm"><h4 class="lr-tab-label">npm</h4><div class="lr-tab-body"><p><code>npm install</code></p></div></section>'."\n"
        .'<section class="lr-tab" data-label="Yarn"><h4 class="lr-tab-label">Yarn</h4><div class="lr-tab-body"><p><code>yarn add</code></p></div></section></div></div>'."\n"
    )->and(Render::markdown($body))->toBe("**npm**\n\n`npm install`\n\n**Yarn**\n\n`yarn add`");
});

it('renders a CodeGroup as an island with one code figure per block', function () {
    $body = "<CodeGroup>\n```bash title=\"Composer\"\ncomposer install\n```\n```txt\nok\n```\n</CodeGroup>";
    $html = Render::html($body)->html;

    expect($html)->toStartWith('<div data-lr-component="CodeGroup" data-lr-props=\'{"labels":["Composer","txt"]}\'><div class="lr-code-group"><figure class="lr-code" data-language="bash" data-title="Composer">')
        ->and(substr_count($html, '<figure class="lr-code"'))->toBe(2)
        ->and($html)->toContain('<figure class="lr-code" data-language="txt"><figcaption class="lr-code-header"><span class="lr-code-title">txt</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">ok</span></code></pre></figure></div></div>')
        ->and(Render::markdown($body))->toBe("```bash title=\"Composer\"\ncomposer install\n```\n\n```txt\nok\n```");
});

it('renders a Card as a link block, or a plain block without href', function () {
    $body = <<<'MD'
<CardGroup cols="3">
<Card title="Webhooks" href="webhooks.md" icon="webhook">
Receive signed events.
</Card>
<Card title="Plain">
No link here.
</Card>
</CardGroup>
MD;

    expect(Render::html($body, [], Render::links())->html)->toBe(
        '<div class="lr-card-group" data-cols="3"><a class="lr-card" href="/docs/webhooks" data-icon="webhook"><span class="lr-card-title">Webhooks</span><span class="lr-card-body">Receive signed events.</span></a>'."\n"
        .'<div class="lr-card"><span class="lr-card-title">Plain</span><span class="lr-card-body">No link here.</span></div></div>'."\n"
    )->and(Render::markdown($body, [], Render::links()))->toBe("- [Webhooks](https://acme.test/docs/webhooks): Receive signed events.\n- **Plain**: No link here.");
});

it('renders Columns', function () {
    expect(Render::html("<Columns cols=\"2\">\nLeft.\n</Columns>")->html)
        ->toBe('<div class="lr-columns" data-cols="2"><p>Left.</p></div>'."\n");
});

it('renders an Accordion as native details, in a group', function () {
    $body = "<AccordionGroup>\n<Accordion title=\"Why?\" open>\nBecause.\n</Accordion>\n</AccordionGroup>";

    expect(Render::html($body)->html)->toBe(
        '<div class="lr-accordion-group"><details class="lr-accordion" open><summary>Why?</summary><div class="lr-accordion-body"><p>Because.</p></div></details></div>'."\n"
    )->and(Render::markdown($body))->toBe("**Why?**\n\nBecause.");
});

it('renders a one-line Badge', function () {
    expect(Render::html('<Badge tone="success">Stable</Badge>')->html)
        ->toBe('<p><span class="lr-badge" data-tone="success">Stable</span></p>'."\n")
        ->and(Render::html('<Badge>New</Badge>')->html)->toContain('data-tone="info"')
        ->and(Render::markdown('<Badge tone="success">Stable</Badge>'))->toBe('**Stable**');
});

it('renders a FileTree around a Markdown list', function () {
    $body = "<FileTree>\n- app\n  - Models\n</FileTree>";

    expect(Render::html($body)->html)->toBe("<div class=\"lr-file-tree\"><ul>\n<li>app\n<ul>\n<li>Models</li>\n</ul>\n</li>\n</ul></div>\n")
        ->and(Render::markdown($body))->toBe("- app\n  - Models");
});

it('renders a Frame with a caption', function () {
    $body = "<Frame caption=\"The flow\">\n![Flow](flow.png)\n</Frame>";

    expect(Render::html($body, [], Render::links())->html)->toBe(
        '<div data-lr-component="Frame" data-lr-props=\'{"caption":"The flow"}\'><figure class="lr-frame"><p><img src="/docs/_assets/current/flow.png" alt="Flow" /></p><figcaption>The flow</figcaption></figure></div>'."\n"
    )->and(Render::markdown($body, [], Render::links()))->toBe("![Flow](https://acme.test/docs/_assets/current/flow.png)\n\n*The flow*");
});

it('renders a Video file as a native video', function () {
    $body = '<Video src="demo.mp4" poster="poster.png" title="Tour" duration="2:40" />';

    expect(Render::html($body, [], Render::links())->html)->toBe(
        '<div data-lr-component="Video" data-lr-props=\'{"src":"/docs/_assets/current/demo.mp4","poster":"/docs/_assets/current/poster.png","title":"Tour","duration":"2:40"}\'>'
        .'<figure class="lr-video"><video controls preload="none" src="/docs/_assets/current/demo.mp4" poster="/docs/_assets/current/poster.png"></video><figcaption>Tour · 2:40</figcaption></figure></div>'."\n"
    )->and(Render::markdown($body, [], Render::links()))->toBe('[Video: Tour · 2:40](https://acme.test/docs/_assets/current/demo.mp4)');
});

it('renders YouTube and Vimeo as click-to-load links with an embed URL for the island', function () {
    $youtube = Render::html('<Video src="https://youtu.be/dQw4w9WgXcQ" title="Demo" />')->html;
    $vimeo = Render::html('<Video src="https://vimeo.com/76979871" poster="https://acme.test/p.png" />')->html;

    expect($youtube)->toContain('"embed":"https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ","provider":"youtube"')
        ->and($youtube)->toContain('<figure class="lr-video"><a class="lr-video-link" href="https://youtu.be/dQw4w9WgXcQ"><span class="lr-video-link-label">Watch on YouTube</span></a><figcaption>Demo</figcaption></figure>')
        ->and($youtube)->not->toContain('<iframe')
        ->and($vimeo)->toContain('"embed":"https://player.vimeo.com/video/76979871"')
        ->and($vimeo)->toContain('<a class="lr-video-link" href="https://vimeo.com/76979871"><img src="https://acme.test/p.png" alt="" loading="lazy"><span class="lr-video-link-label">Watch on Vimeo</span></a>');
});

it('renders a Video without src as a labelled placeholder and reports it', function () {
    $rendered = Render::html('<Video placeholder="Demo: the maintainer explains LaRecipe (2 min)" />');

    expect($rendered->html)->toContain('<figure class="lr-video lr-video-placeholder"><div class="lr-video-frame"><span>Demo: the maintainer explains LaRecipe (2 min)</span></div></figure>')
        ->and($rendered->issues)->toHaveCount(1)
        ->and($rendered->issues[0])->toMatchArray(['rule' => 'media.placeholder', 'level' => 'warning', 'component' => 'Video'])
        ->and($rendered->hasErrors())->toBeFalse()
        ->and(Render::html('<Video />')->html)->toContain('<span>Demo video coming soon</span>')
        ->and(Render::markdown('<Video placeholder="Demo (2 min)" />'))->toBe('*Video coming soon: Demo (2 min)*');
});

it('renders Mermaid from a tag and from a fence, keeping the source as text', function () {
    $tag = "<Mermaid>\ngraph TD\n  A-->B\n</Mermaid>";
    $fence = "```mermaid\ngraph TD\n  A-->B\n```";
    $html = Render::html($tag)->html;

    preg_match("/data-lr-props='([^']*)'/", $html, $props);

    expect($html)->toStartWith('<div data-lr-component="Mermaid" data-lr-props=')
        ->and($html)->toEndWith("<pre class=\"lr-mermaid\"><code>graph TD\n  A--&gt;B</code></pre></div>\n")
        ->and(json_decode($props[1], true))->toBe(['source' => "graph TD\n  A-->B"])
        ->and(Render::html($fence)->html)->toBe($html)
        ->and(Render::markdown($tag))->toBe("```mermaid\ngraph TD\n  A-->B\n```");
});

it('renders Math from $$ blocks and the Math tag without parsing the TeX as Markdown', function () {
    $expected = '<div data-lr-component="Math" data-lr-props=\'{"source":"a_1 * b_2","display":"block"}\'><div class="lr-math" data-display="block"><code>a_1 * b_2</code></div></div>'."\n";

    expect(Render::html("$$\na_1 * b_2\n$$")->html)->toBe($expected)
        ->and(Render::html('$$ a_1 * b_2 $$')->html)->toBe($expected)
        ->and(Render::html("<Math>\na_1 * b_2\n</Math>")->html)->toBe($expected)
        ->and(Render::html('<Math>a_1 * b_2</Math>')->html)->toBe($expected)
        ->and(Render::markdown("$$\na_1 * b_2\n$$"))->toBe("$$\na_1 * b_2\n$$");
});

it('renders a Button as a link', function () {
    $body = '<Button href="installation.md" variant="outline">Install</Button>';

    expect(Render::html($body, [], Render::links())->html)->toBe('<a class="lr-button lr-button-outline" href="/docs/installation">Install</a>'."\n")
        ->and(Render::html('<Button href="/start">Start</Button>')->html)->toBe('<a class="lr-button lr-button-primary" href="/start">Start</a>'."\n")
        ->and(Render::markdown($body, [], Render::links()))->toBe('[Install](https://acme.test/docs/installation)');
});

it('drops unsafe URLs from component props', function () {
    $html = Render::html('<Button href="javascript:alert(1)">Click</Button>')->html;

    expect($html)->not->toContain('javascript:')->and($html)->toContain('href=""');
});

it('escapes unknown components and reports them', function () {
    $rendered = Render::html("<Nope>\nText\n</Nope>");

    expect($rendered->html)->toContain('<p>&lt;Nope&gt;</p>')
        ->and($rendered->issues[0])->toMatchArray(['rule' => 'components.unknown', 'level' => 'error'])
        ->and(Render::markdown("<Nope>\nText\n</Nope>"))->toBe('Text');
});

it('nests components with the same name', function () {
    $body = "<Tabs>\n<Tab label=\"Outer\">\n<Tabs>\n<Tab label=\"Inner\">\nDeep.\n</Tab>\n</Tabs>\n</Tab>\n</Tabs>\n\nAfter.";
    $html = Render::html($body)->html;

    expect(substr_count($html, 'class="lr-tabs"'))->toBe(2)
        ->and($html)->toEndWith("</section></div></div>\n<p>After.</p>\n");
});
