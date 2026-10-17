---
title: Components
description: Callouts, steps, tabs, code groups, cards and media. Each one renders as server HTML that reads without JavaScript, and as plain Markdown for agents.
---

# Components

Components are tags in your Markdown. The server renders each one as HTML that works without JavaScript; the browser adds behavior where it helps.

## Callouts

<aside class="lr-callout" data-type="note" role="note"><div class="lr-callout-label">Note</div><div class="lr-callout-body"><p>A callout gets its meaning from the label, never from a tinted background.</p></div></aside>

<aside class="lr-callout" data-type="tip" role="note"><div class="lr-callout-label">Tip</div><div class="lr-callout-body"><p>Run <code>php artisan larecipe:validate</code> before you push to catch broken links.</p></div></aside>

<aside class="lr-callout" data-type="important" role="note"><div class="lr-callout-label">Important</div><div class="lr-callout-body"><p>Agent edits made outside a pull request stay drafts until a person publishes them.</p></div></aside>

<aside class="lr-callout" data-type="warning" role="note"><div class="lr-callout-label">Warning</div><div class="lr-callout-body"><p>Verify the signature before you trust a webhook payload.</p></div></aside>

<aside class="lr-callout" data-type="danger" role="note"><div class="lr-callout-label">Danger</div><div class="lr-callout-body"><p>Never put secrets in docs. There is no <code>&lt;Config&gt;</code> component, on purpose.</p></div></aside>

## Steps

<ol class="lr-steps"><li class="lr-step"><div class="lr-step-title">Write the page</div><div class="lr-step-body"><p>Add a Markdown file under <code>docs/</code>. Frontmatter holds page-level settings only.</p></div></li><li class="lr-step"><div class="lr-step-title">Validate</div><div class="lr-step-body"><p>The validator checks links, components and references, and prints JSON for agents.</p></div></li><li class="lr-step"><div class="lr-step-title">Ship it with the code</div><div class="lr-step-body"><p>The docs change rides in the same pull request as the code it describes.</p></div></li></ol>

## Tabs

<div data-lr-component="Tabs" data-lr-props='{"labels": ["Composer", "Laravel Cloud", "Docker"]}'><div class="lr-tabs"><section class="lr-tab" data-label="Composer"><h4 class="lr-tab-label">Composer</h4><div class="lr-tab-body"><p>Install with Composer and publish nothing: the UI is served from the package.</p></div></section><section class="lr-tab" data-label="Laravel Cloud"><h4 class="lr-tab-label">Laravel Cloud</h4><div class="lr-tab-body"><p>Provenance is compiled at build time, because production servers have no git history.</p></div></section><section class="lr-tab" data-label="Docker"><h4 class="lr-tab-label">Docker</h4><div class="lr-tab-body"><p>Run <code>php artisan larecipe:optimize</code> in your image build, next to <code>route:cache</code>.</p></div></section></div></div>

## Code

<figure class="lr-code" data-language="php" data-title="app/Http/Controllers/WebhookController.php"><figcaption class="lr-code-header"><span class="lr-code-title">app/Http/Controllers/WebhookController.php</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-keyword">public function</span> <span class="tok-function">__invoke</span>(Request $request): Response</span>
<span class="lr-line" data-line="2">{</span>
<span class="lr-line" data-line="3" data-highlighted>    $event = <span class="tok-constant">Webhook</span>::<span class="tok-function">verify</span>($request, <span class="tok-function">config</span>(<span class="tok-string">'billing.secret'</span>));</span>
<span class="lr-line" data-line="4">    <span class="tok-comment">// Retries arrive with the same id; handle them once.</span></span>
<span class="lr-line" data-line="5">    <span class="tok-keyword">return</span> <span class="tok-function">response</span>()-><span class="tok-function">noContent</span>();</span>
<span class="lr-line" data-line="6">}</span></code></pre></figure>

A plain fenced block:

```bash
php artisan larecipe:validate --json
```

### Code groups

<div data-lr-component="CodeGroup" data-lr-props='{"labels":["npm","pnpm","yarn"],"sync":"pm"}'><div class="lr-code-group"><figure class="lr-code" data-language="bash" data-title="npm"><figcaption class="lr-code-header"><span class="lr-code-title">npm</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">npm install -D @larecipe/vite</span></code></pre></figure><figure class="lr-code" data-language="bash" data-title="pnpm"><figcaption class="lr-code-header"><span class="lr-code-title">pnpm</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">pnpm add -D @larecipe/vite</span></code></pre></figure><figure class="lr-code" data-language="bash" data-title="yarn"><figcaption class="lr-code-header"><span class="lr-code-title">yarn</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">yarn add -D @larecipe/vite</span></code></pre></figure></div></div>

Install the Vite plugin the same way:

<div data-lr-component="CodeGroup" data-lr-props='{"labels":["npm","pnpm","yarn"],"sync":"pm"}'><div class="lr-code-group"><figure class="lr-code" data-language="bash" data-title="npm"><figcaption class="lr-code-header"><span class="lr-code-title">npm</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">npm run build</span></code></pre></figure><figure class="lr-code" data-language="bash" data-title="pnpm"><figcaption class="lr-code-header"><span class="lr-code-title">pnpm</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">pnpm build</span></code></pre></figure><figure class="lr-code" data-language="bash" data-title="yarn"><figcaption class="lr-code-header"><span class="lr-code-title">yarn</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">yarn build</span></code></pre></figure></div></div>

## Cards and columns

<div class="lr-card-group" data-cols="3"><a class="lr-card" href="/guide/3.x/installation"><span class="lr-card-title">Installation</span><span class="lr-card-body">Require the package and run the installer.</span></a><a class="lr-card" href="/guide/3.x/configuration"><span class="lr-card-title">Configuration</span><span class="lr-card-body">Routes, sources, search and access.</span></a><a class="lr-card" href="/guide/3.x/agents"><span class="lr-card-title">Agents</span><span class="lr-card-body">MCP, skills and the pre-push hook.</span></a></div>

<div class="lr-columns" data-cols="2"><div><p><strong>For readers.</strong> Search as you type, provenance on every page, Arabic and other right-to-left languages as peer renderings.</p></div><div><p><strong>For agents.</strong> Markdown twins, <code>llms.txt</code>, an MCP server and page actions next to the table of contents.</p></div></div>

## Accordion

<details class="lr-accordion"><summary>Does LaRecipe need Node on my server?</summary><div class="lr-accordion-body"><p>No. The UI is built before release and served from the package. Node is only needed on a developer machine if you write your own components.</p></div></details>
<details class="lr-accordion"><summary>Can I keep docs private?</summary><div class="lr-accordion-body"><p>Yes. A doc set can require authentication or a gate, and the same rule applies to search, Markdown and MCP.</p></div></details>

## Badges

New in 3.0 <span class="lr-badge" data-tone="info">New</span>, still supported <span class="lr-badge" data-tone="success">Stable</span>, on the way out <span class="lr-badge" data-tone="warning">Deprecated</span>, and gone <span class="lr-badge" data-tone="danger">Removed</span>.

## File tree

<div class="lr-file-tree"><ul><li><span>docs/</span><ul><li><span>larecipe.json</span></li><li><span>3.x/</span><ul><li><span>navigation.json</span></li><li><span>index.md</span></li><li><span>installation.md</span></li></ul></li></ul></li><li><span>resources/larecipe/</span><ul><li><span>extension.tsx</span></li></ul></li></ul></div>

## Media

<div data-lr-component="Frame" data-lr-props='{"zoom":true}'><figure class="lr-frame"><img src="/guide/_assets/3.x/loop.svg" alt="The loop: code, ticket, review, page, agent" width="1200" height="520"><figcaption>The loop</figcaption></figure></div>

<div data-lr-component="Video" data-lr-props='{"src":"https://www.youtube.com/watch?v=aqz-KE-bpKQ","embed":"https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ","provider":"youtube","title":"LaRecipe in two minutes","duration":"2:40","poster":"/guide/_assets/3.x/loop.svg"}'><figure class="lr-video"><a class="lr-video-link" href="https://www.youtube.com/watch?v=aqz-KE-bpKQ"><img src="/guide/_assets/3.x/loop.svg" alt="" loading="lazy"><span class="lr-video-link-label">Watch on YouTube</span></a><figcaption>LaRecipe in two minutes · 2:40</figcaption></figure></div>

## Diagrams

<div data-lr-component="Mermaid" data-lr-props='{}'><pre class="lr-mermaid"><code>flowchart LR
  code[Code change] --> ticket[Ticket]
  ticket --> review{Review}
  review -->|approved| page[Page updated]
  page --> agent[Agents read .md]</code></pre></div>

## Math

<div data-lr-component="Math" data-lr-props='{"display":true}'><div class="lr-math" data-display="block"><code>\text{reading time} = \left\lceil \frac{\text{words}}{220} \right\rceil</code></div></div>

## Tables

| Surface | Format | Who reads it |
| --- | --- | --- |
| `/docs/installation` | HTML | People |
| `/docs/installation.md` | Markdown | Agents |
| `/docs/llms.txt` | Index | Agents |

## Buttons

<p><a class="lr-button lr-button-secondary" href="/guide/3.x/installation">Install LaRecipe</a> <a class="lr-button lr-button-outline" href="/guide/3.x/agents">Connect agents</a></p>

---

> Docs that agents maintain still need a person at the pass.
