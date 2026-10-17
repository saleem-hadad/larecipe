---
title: Installation
description: Add LaRecipe to any Laravel app. Your docs live next to your code, and your coding agents keep them current.
---

# Installation

## Requirements

<p><span class="lr-badge">PHP 8.3+</span> <span class="lr-badge">Laravel 12 or 13</span> <span class="lr-badge">Composer 2</span></p>

LaRecipe needs no Node.js on your servers. The UI ships prebuilt inside the package.

## Install the package

<ol class="lr-steps"><li class="lr-step"><div class="lr-step-title">Require the package</div><div class="lr-step-body"><figure class="lr-code" data-language="bash" data-title="Terminal"><figcaption class="lr-code-header"><span class="lr-code-title">Terminal</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> composer require <span class="tok-string">larecipe/larecipe</span></span></code></pre></figure></div></li><li class="lr-step"><div class="lr-step-title">Run the installer</div><div class="lr-step-body"><p>It asks where your docs live and which agents you use, then wires everything up. Nothing is written without a preview.</p><figure class="lr-code" data-language="bash" data-title="Terminal"><figcaption class="lr-code-header"><span class="lr-code-title">Terminal</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> php artisan <span class="tok-keyword">larecipe:install</span></span>
<span class="lr-line" data-line="2">  Where should your docs live? <span class="tok-comment">›</span> docs/</span>
<span class="lr-line" data-line="3">  Connect coding agents? <span class="tok-comment">›</span> Claude Code, Codex, Cursor</span>
<span class="lr-line" data-line="4">  <span class="tok-string">✓</span> Wrote docs/larecipe.json</span>
<span class="lr-line" data-line="5">  <span class="tok-string">✓</span> Registered MCP server <span class="tok-string">"larecipe"</span></span></code></pre></figure></div></li><li class="lr-step"><div class="lr-step-title">Connect your agents</div><div class="lr-step-body"><div data-lr-component="CodeGroup" data-lr-props='{"labels":["Claude Code","Codex","Cursor"]}'><div class="lr-code-group"><figure class="lr-code" data-language="bash" data-title="Claude Code"><figcaption class="lr-code-header"><span class="lr-code-title">Claude Code</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> claude mcp add larecipe -- php artisan <span class="tok-keyword">mcp:start</span> larecipe</span></code></pre></figure><figure class="lr-code" data-language="bash" data-title="Codex"><figcaption class="lr-code-header"><span class="lr-code-title">Codex</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> codex mcp add larecipe -- php artisan <span class="tok-keyword">mcp:start</span> larecipe</span></code></pre></figure><figure class="lr-code" data-language="json" data-title="Cursor"><figcaption class="lr-code-header"><span class="lr-code-title">Cursor</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">{</span>
<span class="lr-line" data-line="2">  <span class="tok-string">"mcpServers"</span>: {</span>
<span class="lr-line" data-line="3">    <span class="tok-string">"larecipe"</span>: { <span class="tok-string">"command"</span>: <span class="tok-string">"php"</span>, <span class="tok-string">"args"</span>: [<span class="tok-string">"artisan"</span>, <span class="tok-string">"mcp:start"</span>, <span class="tok-string">"larecipe"</span>] }</span>
<span class="lr-line" data-line="4">  }</span>
<span class="lr-line" data-line="5">}</span></code></pre></figure></div></div></div></li></ol>

<aside class="lr-callout" data-type="note" role="note"><div class="lr-callout-label">Agent-ready by default</div><div class="lr-callout-body"><p>Every page is also served as Markdown at <code>/docs/installation.md</code>, listed in <code>llms.txt</code>, and searchable through the MCP server.</p></div></aside>

## Choose a source

Docs live in your repository by default. Teams that want an admin UI can keep them in the database instead.

<div data-lr-component="Tabs" data-lr-props='{"labels": ["Repository", "Database"]}'><div class="lr-tabs"><section class="lr-tab" data-label="Repository"><h4 class="lr-tab-label">Repository</h4><div class="lr-tab-body"><p>Markdown files in <code>docs/</code>, reviewed in the same pull request as the code. This is the recommended source.</p></div></section><section class="lr-tab" data-label="Database"><h4 class="lr-tab-label">Database</h4><div class="lr-tab-body"><p>Pages stored in your database and edited in the admin UI, always served through a cache. Changes made in a pull request travel as docs migrations.</p></div></section></div></div>

## Configure the route

The docs answer at `/docs` by default. Change the prefix in `config/larecipe.php`:

<figure class="lr-code" data-language="php" data-title="config/larecipe.php"><figcaption class="lr-code-header"><span class="lr-code-title">config/larecipe.php</span></figcaption><pre class="lr-pre" tabindex="0" data-diff><code><span class="lr-line" data-line="1"><span class="tok-string">'route'</span> => [</span>
<span class="lr-line" data-line="2" data-diff="remove">    <span class="tok-string">'prefix'</span> => <span class="tok-string">'docs'</span>,</span>
<span class="lr-line" data-line="3" data-diff="add">    <span class="tok-string">'prefix'</span> => <span class="tok-function">env</span>(<span class="tok-string">'LARECIPE_PREFIX'</span>, <span class="tok-string">'help'</span>),</span>
<span class="lr-line" data-line="4" data-highlighted>    <span class="tok-string">'middleware'</span> => [<span class="tok-string">'web'</span>, <span class="tok-string">'auth'</span>],</span>
<span class="lr-line" data-line="5">],</span></code></pre></figure>

<aside class="lr-callout" data-type="warning" role="note"><div class="lr-callout-label">Warning</div><div class="lr-callout-body"><p>Private docs must stay behind your own middleware. LaRecipe applies the same access rules to HTML, Markdown, search and MCP, but the route is yours to protect.</p></div></aside>

## Next steps

Read how [components](components.md) render, or [connect your agents](agents.md).
