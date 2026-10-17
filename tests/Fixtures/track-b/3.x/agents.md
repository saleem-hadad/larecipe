---
title: Connect your agents
description: Give Claude Code, Codex and Cursor the same docs your readers see, through MCP and Markdown.
---

# Connect your agents

Coding agents read your docs through the MCP server, the `.md` twin of every page and `llms.txt`. They write docs through the same tools, and a person signs the changes off.

## The MCP server

Each doc set has an MCP endpoint. Add it to Claude Code with one command:

<figure class="lr-code" data-language="bash" data-title="Terminal"><figcaption class="lr-code-header"><span class="lr-code-title">Terminal</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> claude mcp add --transport http larecipe https://larecipe.saleem.dev/docs/mcp</span></code></pre></figure>

## Skills and hooks

The installer writes four skills and a pre-push hook. The hook holds a push once when the affected pages were not updated.

## Page actions

Every page offers Copy as Markdown, View as Markdown and Open in Claude, ChatGPT or Cursor, next to the table of contents.
