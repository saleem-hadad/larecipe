# Built-in components: the HTML contract

What the server renders for every built-in component, code block and search index, so the UI can style and enhance it. The renderer tests (`tests/Unit/Markdown/ComponentsTest.php`, `CodeBlocksTest.php`, `DirectivesTest.php`) pin this markup; change it here and there together.

Every component renders complete, readable HTML without JavaScript. Components marked **island** are also wrapped in a placeholder the React runtime mounts into:

```html
<div data-lr-component="Tabs" data-lr-props='{"sync":"pm","labels":["npm","Yarn"]}'>
  ...the fallback HTML below...
</div>
```

`data-lr-props` is JSON: the author's props, defaults filled in, plus the derived props listed per component. URL props (`href`, `src`, `poster`) arrive already resolved to reader URLs; unsafe schemes (`javascript:`) arrive as an empty string. Non-island components have no wrapper.

## Three spellings, one node

A component can be written three ways and always renders the same HTML:

| Syntax | Example |
| --- | --- |
| Tag | `<Callout type="warning" title="Heads up">` ... `</Callout>` |
| Directive | `:::warning Heads up` ... `:::` (or `:::callout {type=warning}`) |
| GitHub alert (Callout only) | `> [!WARNING] Heads up` |

Directives nest with longer fences (`::::tabs` around `:::tab`); equal fences close the innermost open directive first. A directive's title text fills the `title` prop, or `label` for a Tab. Kebab-case names map to components (`:::code-group` is `CodeGroup`).

## Components

| Component | Island | HTML |
| --- | --- | --- |
| Callout | no | `<aside class="lr-callout" data-type="{note,tip,important,warning,caution,danger,info,success}" role="note"><div class="lr-callout-label">Title or Type</div><div class="lr-callout-body">...</div></aside>` |
| Steps | no | `<ol class="lr-steps">` + one `Step` per item + `</ol>` |
| Step | no | `<li class="lr-step"><div class="lr-step-title">T</div><div class="lr-step-body">...</div></li>` |
| Tabs | `Tabs` | `<div class="lr-tabs">` + one `Tab` per panel + `</div>`. Props: `default?`, `sync?`, derived `labels` (every Tab label, in order) |
| Tab | no | `<section class="lr-tab" data-label="L"><h4 class="lr-tab-label">L</h4><div class="lr-tab-body">...</div></section>` |
| CodeGroup | `CodeGroup` | `<div class="lr-code-group">` + one code figure per block + `</div>`. Derived prop `labels` (each block's title, else language) |
| Card | no | `<a class="lr-card" href="..." [data-icon="name"]><span class="lr-card-title">T</span><span class="lr-card-body">...</span></a>`, or `<div class="lr-card">` without `href`. A one-paragraph body is unwrapped (no `<p>`) |
| CardGroup | no | `<div class="lr-card-group" data-cols="2">...</div>` (`cols` defaults to 2) |
| Columns | no | `<div class="lr-columns" data-cols="2">...</div>` |
| Accordion | no | `<details class="lr-accordion" [open]><summary>T</summary><div class="lr-accordion-body">...</div></details>` |
| AccordionGroup | no | `<div class="lr-accordion-group">...</div>` |
| Badge | no | `<p><span class="lr-badge" data-tone="{neutral,info,success,warning,danger}">text</span></p>` (tone defaults to info). Written on one line: `<Badge tone="success">Stable</Badge>` |
| Button | no | `<a class="lr-button lr-button-{primary,secondary,outline,ghost}" href="...">text</a>` |
| FileTree | no | `<div class="lr-file-tree">` + the author's Markdown list (`<ul>`) + `</div>` |
| Frame | `Frame` | `<figure class="lr-frame">...children...<figcaption>caption</figcaption></figure>` (no figcaption without `caption`) |
| Video | `Video` | See below |
| Mermaid | `Mermaid` | `<pre class="lr-mermaid"><code>escaped source</code></pre>`. Derived prop `source`. A ```` ```mermaid ```` fence renders the same |
| Math | `Math` | `<div class="lr-math" data-display="block"><code>escaped TeX</code></div>`. Derived prop `source`. `$$ ... $$` blocks render the same |

### Video

- **File** (`src` is not YouTube or Vimeo): `<figure class="lr-video"><video controls preload="none" src="..." [poster="..."]></video><figcaption>Title · 2:40</figcaption></figure>`
- **YouTube or Vimeo**: a click-to-load link, nothing third-party loads until the reader asks: `<figure class="lr-video"><a class="lr-video-link" href="{src}">[<img src="{poster}" alt="" loading="lazy">]<span class="lr-video-link-label">Watch on YouTube</span></a><figcaption>...</figcaption></figure>`. Props carry `embed` (`https://www.youtube-nocookie.com/embed/{id}` or `https://player.vimeo.com/video/{id}`) and `provider` (`youtube` or `vimeo`) for the island's player.
- **No src**: `<figure class="lr-video lr-video-placeholder"><div class="lr-video-frame"><span>{placeholder, or the video.placeholder UI string in the page's language ("Demo video coming soon")}</span></div>[<figcaption>...</figcaption>]</figure>`, and the render reports a `media.placeholder` warning.

The figcaption is `title · duration`, leaving out what is empty, and is omitted when both are.

## Code blocks

Every fenced or indented code block, inside a CodeGroup or not:

```html
<figure class="lr-code" data-language="php" data-title="app/Billing/Invoice.php">
  <figcaption class="lr-code-header"><span class="lr-code-title">app/Billing/Invoice.php</span></figcaption>
  <pre class="lr-pre" tabindex="0" [data-line-numbers] [data-diff]><code><span class="lr-line" data-line="1">...</span>
<span class="lr-line" data-line="2" data-highlighted>...</span>
<span class="lr-line" data-line="3" data-diff="add">...</span></code></pre>
</figure>
```

- `data-title` is present only when the block has a title; the header shows the title, else the language, else `text`. The UI adds the copy button.
- Info-string meta: `title="..."` (or `[title]`), `{2,4-6}` highlights (`data-highlighted` on those lines), `showLineNumbers` (`data-line-numbers` on the pre), `diff` (`data-diff` on the pre, `data-diff="add|remove"` on changed lines; the `+`/`-` marker is removed so the line highlights as its language). A ```` ```diff ```` block keeps its markers and marks lines the same way.
- Lines are joined by newlines inside `<code>`, so copying the text gives the code. Tokens carry inline colors from the single `larecipe-pass` theme (code sits on a dark slab in both modes), for example `<span style="color:#B4A7FF">function</span>`. Unknown languages are escaped text.
- `<<< @/app/Billing/Invoice.php#refund` renders as a code block titled with the path. A missing file or region renders a warning Callout titled "Snippet not found".

## Render issues

`RenderedPage::$issues` entries are `{rule, level, message, component?, line?}`. Rules: `components.unknown`, `components.props` (errors), `snippets.missing`, `snippets.denied`, `snippets.disabled` (errors), `media.placeholder` (warning). Renders with errors are not cached.

## Agent Markdown forms

| Component | Markdown |
| --- | --- |
| Callout | `> [!WARNING]` alert, the title as a bold first line |
| Steps / Step | a numbered list, each step `1. **Title**` with its body indented |
| Tabs / Tab | each tab as `**Label**` followed by its content |
| CodeGroup | the code fences, each with its title |
| Card / CardGroup | `- [Title](url): description` (or `- **Title**: description`) |
| Accordion | `**Title**` followed by its content |
| Badge | `**text**` |
| Button | `[text](url)` |
| Frame | the media, then `*caption*` |
| Video | `[Video: Title · 2:40](src)`, or `*Video coming soon: {placeholder}*` |
| Mermaid | a ```` ```mermaid ```` fence |
| Math | a `$$` block |
| Columns, AccordionGroup, FileTree | their children |
| Unknown or host components without a Markdown form | their children |

URLs in agent Markdown are absolute.

## Search index (for the palette)

`boot.search` is `{ driver, index_url, endpoint }`. With the `client` driver, `index_url` is `/{prefix}[/{locale}][/{version}]/_search/{audience}.{fingerprint}.json`: immutable, holding only records this reader may see. With `server` or a custom driver, `index_url` is null and the UI calls `endpoint` (`GET {endpoint}?q=...&limit=10`, returning `{ query, hits: [{ title, heading, anchor, path, url, markdown_url, snippet, score, description }] }`).

The index JSON loads straight into MiniSearch; the browser indexes the records itself:

```js
const { fields, storeFields, searchOptions, records } = await (await fetch(boot.search.index_url)).json()
const search = new MiniSearch({ fields, storeFields, searchOptions })
search.addAll(records)
```

Each record is `{ id, title, heading, tags, text, terms, path, url, kind }`: `title` is the page title, `heading` the section (null for the page intro), `url` the page path with the section anchor, `kind` is `page` or `section` (for the Pages and Sections groups). `terms` holds identifier parts the default tokenizer misses (`has many` for `hasMany`). `searchOptions` uses `combineWith: "AND"`; retry with `"OR"` when a query finds nothing.
