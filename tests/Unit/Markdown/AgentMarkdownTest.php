<?php

/*
 * The agent Markdown serializer: a projection of the same AST the HTML comes
 * from, with a stable header, absolute links and components in portable form.
 */

use LaRecipe\Domains\Markdown\Support\AbsoluteUrls;
use LaRecipe\Tests\Unit\Markdown\Render;

it('writes a stable header, the discovery hint and the title', function () {
    $document = Render::document("Body text.", [
        'version' => '3.x',
        'llms' => 'https://acme.test/docs/llms.txt',
        'mcp' => 'https://acme.test/docs/mcp',
        'skill' => 'https://acme.test/docs/skill.md',
    ]);

    expect($document)->toBe(<<<'MD'
---
title: Example
url: 'https://acme.test/docs/guides/example'
version: 3.x
---

> Docs index: https://acme.test/docs/llms.txt · MCP: https://acme.test/docs/mcp · Skill: https://acme.test/docs/skill.md

# Example

Body text.

MD);
});

it('keeps the page H1 instead of adding one', function () {
    expect(Render::document("# Webhooks\n\nText."))->toContain("\n# Webhooks\n\nText.\n")
        ->and(substr_count(Render::document("# Webhooks\n\nText."), '# '))->toBe(1);
});

it('never copies the source frontmatter', function () {
    $page = Render::page('Text.', 'example', ['secret_note' => 'internal', 'sources' => ['app/**']]);
    $document = app(\LaRecipe\Domains\Markdown\Actions\RenderAgentMarkdownAction::class)->execute($page, ['url' => Render::URL]);

    expect($document)->not->toContain('internal')->and($document)->not->toContain('app/**');
});

it('serializes headings, emphasis, inline code and lists', function () {
    $body = <<<'MD'
## Install

Use **bold**, *italic*, ~~old~~ and `code`.

- one
- two
  - nested

3. three
4. four

- [x] done
- [ ] todo
MD;

    expect(Render::markdown($body))->toBe($body);
});

it('serializes loose lists with several blocks per item', function () {
    $body = "1. First\n\n   More about it.\n\n2. Second";

    expect(Render::markdown($body))->toBe($body);
});

it('serializes GFM tables with alignment', function () {
    $body = "| Name | Size |\n| :--- | ---: |\n| a \\| b | 2 |";

    expect(Render::markdown($body))->toBe($body);
});

it('serializes code fences with language and title, longer fences when needed', function () {
    expect(Render::markdown("```php title=\"app/X.php\" {2}\n\$a = 1;\n```"))->toBe("```php title=\"app/X.php\"\n\$a = 1;\n```")
        ->and(Render::markdown("````md\n```js\nx\n```\n````"))->toBe("````md\n```js\nx\n```\n````")
        ->and(Render::markdown("    indented\n"))->toBe("```\nindented\n```")
        ->and(Render::markdown("```php diff\n+ \$a;\n```"))->toBe("```php diff\n+ \$a;\n```");
});

it('makes links and images absolute and resolves .md links like the HTML does', function () {
    $markdown = Render::markdown("See [install](../installation.md#env), [home](/), [x](https://x.test) and [top](#intro).\n\n![Flow](flow.png \"The flow\")", [], Render::links());

    expect($markdown)->toBe("See [install](https://acme.test/docs/installation#env), [home](https://acme.test/), [x](https://x.test) and [top](https://acme.test/docs/guides/example#intro).\n\n![Flow](https://acme.test/docs/_assets/current/flow.png \"The flow\")");
});

it('resolves relative links against the page URL without a link resolver', function () {
    expect(Render::markdown('[a](other) [b](../up)'))->toBe('[a](https://acme.test/docs/guides/other) [b](https://acme.test/docs/up)');
});

it('uses the base URL from the meta for domain-relative links', function () {
    $document = Render::document('[home](/docs)', ['base' => 'https://docs.acme.test']);

    expect($document)->toContain('[home](https://docs.acme.test/docs)');
});

it('keeps autolinks, blockquotes, breaks and footnotes', function () {
    $body = "Visit <https://acme.test>.\n\n> Quoted\n> text\n\nLine one\\\nline two\n\nA claim[^src].\n\n[^src]: The source.";

    expect(Render::markdown($body))->toBe("Visit <https://acme.test>.\n\n> Quoted\n> text\n\nLine one\\\nline two\n\nA claim[^src].\n\n[^src]: The source.");
});

it('escapes only what would change meaning', function () {
    expect(Render::markdown('Use snake_case, 2 \* 3 and \`ticks\`.'))->toBe('Use snake_case, 2 \* 3 and \`ticks\`.');
});

it('degrades components through their Markdown forms, children first', function () {
    $body = <<<'MD'
<Steps>
<Step title="Install">
<Callout type="tip">
Use **Composer**.
</Callout>
</Step>
</Steps>
MD;

    expect(Render::markdown($body))->toBe("1. **Install**\n\n   > [!TIP]\n   > Use **Composer**.");
});

it('renders host components through their markdown closure, and unknown ones as children', function () {
    app(\LaRecipe\LaRecipe::class)->component('Subscribe')
        ->props(['list' => ['type' => 'string', 'default' => 'larecipe']])
        ->fallback(fn (array $props) => '<form></form>')
        ->markdown(fn (array $props) => 'Subscribe to the '.$props['list'].' newsletter: https://acme.test/newsletter');

    expect(Render::markdown('<Subscribe />'))->toBe('Subscribe to the larecipe newsletter: https://acme.test/newsletter')
        ->and(Render::markdown("<Mystery>\nKept **text**.\n</Mystery>"))->toBe('Kept **text**.');
});

it('resolves URLs like a browser', function (string $url, string $expected) {
    expect((new AbsoluteUrls('https://acme.test/docs/guides/webhooks?x=1#top'))($url))->toBe($expected);
})->with([
    ['https://x.test/a', 'https://x.test/a'],
    ['mailto:a@b.test', 'mailto:a@b.test'],
    ['//cdn.test/a.png', 'https://cdn.test/a.png'],
    ['/docs/x', 'https://acme.test/docs/x'],
    ['#retries', 'https://acme.test/docs/guides/webhooks?x=1#retries'],
    ['events', 'https://acme.test/docs/guides/events'],
    ['./events#a', 'https://acme.test/docs/guides/events#a'],
    ['../../../x', 'https://acme.test/x'],
]);
