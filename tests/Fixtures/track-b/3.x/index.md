---
title: Introduction
description: Living documentation for Laravel apps. Your docs live next to your code, and your coding agents keep them current.
---

# Introduction

LaRecipe turns a folder of Markdown inside your Laravel app into a docs site that people and agents can read. Pages are served as HTML for readers, as Markdown for agents, and through an MCP server for coding tools.

<figure class="lr-video lr-video-placeholder"><div class="lr-video-frame"><span>Demo video coming soon</span></div><figcaption>Demo · 2:40</figcaption></figure>

## What you get

- Docs in the same repository as the code they describe, reviewed in the same pull request.
- A page that says when it changed, what changed it and who approved it.
- Search that answers as you type, and Ask for questions the search cannot.
- Every page as Markdown at its `.md` URL, listed in `llms.txt`.

<div class="lr-card-group" data-cols="2"><a class="lr-card" href="/guide/3.x/installation"><span class="lr-card-title">Install LaRecipe</span><span class="lr-card-body">Require the package, run the installer and serve your first page.</span></a><a class="lr-card" href="/guide/3.x/components"><span class="lr-card-title">Write with components</span><span class="lr-card-body">Callouts, steps, tabs and code groups that read well without JavaScript.</span></a><a class="lr-card" href="/guide/3.x/agents"><span class="lr-card-title">Connect your agents</span><span class="lr-card-body">Give Claude Code, Codex and Cursor the same docs your readers see.</span></a><a class="lr-card" href="/guide/3.x/guides/webhooks"><span class="lr-card-title">Receive webhooks</span><span class="lr-card-body">Verify signatures and retry failed deliveries.</span></a></div>

<Callout type="tip">
Every page is also served as Markdown. Add `.md` to any URL.
</Callout>

## How it works

Your coding agent updates the affected pages before it pushes. A reviewer reads the docs diff next to the code diff. When the pull request merges, the page says so.

<div data-lr-component="Frame" data-lr-props='{"zoom":true}'><figure class="lr-frame"><img src="/guide/_assets/3.x/loop.svg" alt="The loop: code, ticket, review, page, agent" width="1200" height="520"><figcaption>The loop · each change, reviewed and published</figcaption></figure></div>

## Next steps

Start with [installation](installation.md), then read how [components](components.md) render for people and agents.
