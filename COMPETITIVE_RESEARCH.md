# LaRecipe v3 · Competitive Research

*Researched 17 October 2026 from live product pages, docs, changelogs, pricing pages, GitHub, npm and Packagist. Where I could, I tested the agent features myself with `curl`.*

In October 2026 every serious docs product is built for agents as well as people. Agents are now the majority readers: in July 2026 they made 66% of requests to Mintlify-hosted docs (15% in January), and GitBook counts 51.8% of intentional reads as agent traffic. The commercial platforms have all landed on the same stack:
- git sync plus a web editor
- a cited "Ask AI" panel
- an agent that turns merged code PRs into docs PRs
- a hosted MCP server
- `llms.txt`, a `.md` version of every page, and `Accept: text/markdown`
- an auto-generated `skill.md`
- agent-traffic analytics

The leaders are Mintlify ($45M Series B at a $500M valuation, $450/mo Pro), documentation.ai (AI included on its free tier), GitBook, ReadMe and Fern (now part of Postman). The paid AI tiers cost $150 to $500 a month, and answers and agent runs are metered on top. The open-source frameworks (Fumadocs, Rspress, Docus, Vocs) now ship the read-side surfaces, but none of them ships AI authoring or code-to-docs sync as a product.

On the Laravel side:
- laravel.com serves Markdown for every docs page.
- `laravel/mcp` v1.0 shipped on 14 September 2026, and `laravel/ai` v1.0 on 23 September 2026.
- LaRecipe is seen as legacy: it still runs Vue 2 and Tailwind 0.7.
- LaraDocs launched in June 2026 with llms.txt, an MCP server and an AI chat, and already has 37% of LaRecipe's monthly installs.

LaRecipe can win by being the only runtime docs engine that is open source, self-hosted and lives inside the host Laravel app. That position lets it:
- read the app's own routes, models, config and gates
- run AI on the owner's keys through `laravel/ai`, with no credit meter
- switch between DB, file and git sources
- ship Mintlify-class agent surfaces and code-to-docs automation for $0

The window is open now. It will not stay open long.

---

## Landscape

**Legend:**
- **.md**: a Markdown URL for each page.
- **CN**: returns Markdown when the request sends `Accept: text/markdown`.
- **MCP**: a hosted MCP server for the docs.
- **Skills**: `skill.md` and/or `/.well-known/agent-skills/index.json`.
- **Menu**: a "Copy page / Open in ChatGPT, Claude" actions menu.

| Tool | Type | Content source | AI for readers | AI for authors | Agent surfaces | Pricing |
|---|---|---|---|---|---|---|
| **Mintlify** | SaaS | Git (MDX + `docs.json`) plus web editor with branches and drafts | Assistant with citations, skills, clarifying questions, narration | Agent in the editor, Slack and MCP; automations on code merge, schedule or webhook; Admin MCP | llms.txt (auto-split), llms-full, .md, CN, MCP + authed MCP, Skills, 18-option Menu, WebMCP, agent analytics | Free (no AI); Pro $450/mo annual; Enterprise |
| **documentation.ai** | SaaS | Git (MDX) plus block web editor | Ask AI with citations, external sources (PDF, URLs) | Agent and automations (update from code, API sync, changelog, self-heal from feedback, link audit); Authoring MCP (32 tools) | llms.txt, .md, CN, reader MCP (OAuth 2.1), Agent Readiness Score | Free (AI included); $55; $159; Enterprise |
| **GitBook** | SaaS | Block editor (hosted) plus two-way Git Sync | Assistant sidebar, embeddable in your product, AI Insights | Agent in change requests, reviewer, background agents; Channels (Slack, GitHub, Linear, Jira); authoring MCP and CLI | llms.txt, llms-full, .md, CN, MCP, Skills, Menu, agent analytics | Free; $65/site + $12/user; $249/site; Enterprise |
| **ReadMe** | SaaS | Tiptap editor ("MDX-ish") plus two-way git | Ask AI ($150/mo add-on) | GitHub AI Writer (PR diff to review branch), Owlbert agent, AI Linter, authoring MCP | .md, CN, `agentSearch` endpoint, MCP with OpenAPI tools, Menu, MCP metrics | Free; Pro $250/mo; Enterprise |
| **Fern** (Postman) | SaaS | Git (`docs.yml` + MDX), WYSIWYG that opens PRs, API specs | Ask Fern in docs, Slack and API | Fern Agent (PRs, schedules, Devin-powered), Fern Writer in Slack | llms.txt per nav node (no llms-full, on purpose), .md, CN, MCP, hosted Skills, Menu, LLM-traffic analytics | Free (10 members); Enterprise |
| **Scalar** | SaaS + OSS core | Git (MD/MDX + `scalar.config.json`), OpenAPI 3.2 | Ask AI on by default, agent chat inside the API reference | Agent Skill, docs MCP, CLI | llms.txt, llms-full, .md, CN, MCP, OpenAPI-to-MCP servers | OSS reference free; Pro $150/mo; Business $600/mo |
| **Redocly** | SaaS + OSS (Redoc) | Git (Markdoc, React pages, OpenAPI, AsyncAPI, GraphQL) | AI search and assistant (Enterprise) | Reviewer agent on PRs; Writer agent planned | llms.txt, CN, RBAC-aware MCP, Skills folder, Menu, WebMCP | $10/seat; $24/seat Enterprise |
| **Archbee** | SaaS | Block editor plus two-way GitHub | Ask AI with sources | Write assist, AI translation, authoring MCP | llms.txt, llms-full, .md, CN, reader MCP for protected docs | From $80/mo; $350/mo; AI tokens extra |
| **Document360** | SaaS | Markdown/WYSIWYG editor, one-way GitHub | Eddy AI search and chatbot | AI writing agent (prompt, video, DOCX), management MCP | llms.txt, .md | Quote only |
| **Theneo** | SaaS | Editor plus two-way GitHub, API specs | Multi-turn Ask AI | Theneo Agent comments on PRs and drafts updates; MCP; skill.md | llms.txt, llms-full, .md, Menu, OpenAPI-to-MCP | Free; $120; $400; Enterprise |
| **Docs7** (Context7) | SaaS | Git; reads Mintlify `docs.json` | Unlimited AI on Pro | Code Watch (docs PR on merge), PR Review agent | llms.txt, llms-full, .md, CN, `/ask` JSON, Skills, WebMCP, AI visibility analytics | Free (3 sites); Pro $200/mo |
| **Fumadocs** | OSS (React, MIT) | Pluggable sources (MDX, Notion, Sanity, Obsidian, remote) | Ask AI via CLI (bring your own model) | Local visual MDX editor | llms.txt, llms-full, .md, CN helper, MCP (CLI), WebMCP (experimental), Menu | Free |
| **Nextra** | OSS (Next.js) | MDX files | Inkeep integration | None | Menu only | Free |
| **Docusaurus** | OSS (React, Meta) | MD/MDX files, content plugins | DocSearch AskAI | None | Plugins only | Free |
| **Starlight** | OSS (Astro) | Content collections and loaders | Plugins | None | Plugins only | Free |
| **VitePress** | OSS (Vue) | Markdown plus Vue | DocSearch AskAI button | None | Plugin (llms.txt, .md) | Free |
| **Rspress** | OSS (React, ByteDance) | MD/MDX | None | Authoring Agent Skills | llms.txt, llms-full, .md, CN, WebMCP on by default, Menu | Free |
| **Zudoku** | OSS (React, Zuplo) | OpenAPI plus MDX | Inkeep | None | llms.txt, llms-full, .md, CN, Menu | Free |
| **Docus** | OSS (Nuxt) | Nuxt Content plus Nuxt Studio (git commits) | Built-in assistant (AI SDK) | Studio AI assistant (announced) | llms.txt, llms-full, .md, CN, MCP, Skills, Menu | Free |
| **Vocs** | OSS (React, wevm) | MD/MDX | Ask AI menu | Guide for writing docs with AI; MCP that also reads the source code | llms.txt, llms-full, .md, MCP with source tools, Markdown audit | Free |
| **DeepWiki** | SaaS (Cognition) | Repo code only | Ask over the wiki | Auto-generated wiki | MCP (3 tools) | Free for public repos; Devin credits for private |
| **Google Code Wiki** | SaaS preview | Repo code | Gemini chat | Regenerates after each change | None yet | Free preview |
| **Context7** | SaaS + OSS MCP | Indexed repos and docs, `context7.json` | Serves agents directly | n/a | MCP (2 tools), CLI + skill, API | Free 1k calls; $10/seat |
| **Promptless** | SaaS | Writes into git docs, Mintlify, GitBook, ReadMe | None | Drafts triggered by PRs, commits, Slack or tickets; review dashboard | None | From $500/mo; free for eligible OSS |
| **Dosu** | SaaS | GitHub/GitLab, Notion, Confluence | Q&A bot | Self-documenting PRs (suggestions posted on the PR) | Read/write MCP, AGENTS.md generation | Free; $16/mo |
| **Swimm** | SaaS | Code-coupled docs | n/a | Pivoted to legacy-code modernization | MCP | Sales only |
| **Doc Holiday** | SaaS | Commits, PRs, tickets | None | One docs PR per release, via CI | None | Free public; $100/mo with your own key |
| **DocuWriter** | SaaS | Repos | None | "Autopilot" drafts on push | MCP (70+ tools) | $49 to $499/mo |
| **GitHub Agentic Workflows** | OSS (GitHub) | Repo | None | Daily Documentation Updater (96% of its PRs merged) | n/a | Free (Actions minutes) |
| **LaRecipe v2** | OSS (Laravel) | Local Markdown + `index.md` | None | None | None | Free |
| **LaraDocs** | OSS (Laravel, Blade) | Local Markdown | AI chat via `laravel/ai` | None | llms.txt, llms-full, MCP (`laravel/mcp`), all filtered by visibility rules | Free |
| **Vellum** | OSS (Laravel, Alpine) | Local Markdown | None | None | llms.txt, raw .md, JSON search, Menu | Free |
| **Scribe** | OSS (Laravel) | Routes, FormRequests, Resources | None | None | OpenAPI, Postman | Free |
| **Scramble** | OSS + PRO (Laravel) | Static analysis to OpenAPI 3.1 | None | Boost guideline and skill | OpenAPI; Scalar renderer | Free; PRO $99/$399 |
| **laravel.com/docs** | Site (Statamic + Inertia React) | `laravel/docs` repo, one branch per version | None (DocSearch v3) | None | .md, CN, "Copy as markdown"; no llms.txt | n/a |
| **Laravel Boost** | OSS (dev dependency) | Hosted docs API (17k+ chunks) | n/a | Guidelines, skills, Search Docs tool | Local MCP, writes AGENTS.md/CLAUDE.md | Free |

### Hosted platforms

**Mintlify.** The pattern-setter, now pitching itself as "the documentation platform for agents."
- **What's good:** the agent stack is complete and it ships weekly:
  - an agent in the editor, Slack and over MCP that researches, plans, writes, validates and then opens a PR
  - automations triggered by code merges (filterable by path or author), schedules, webhooks or integrations
  - an Admin MCP that agents edit through, with branches, diffs, comments and publishing
  - hosted search MCP at `/mcp` and `/authed/mcp`, discoverable via `/.well-known/mcp`
  - auto-generated `skill.md` and an agent-skills index
  - scoped `llms.txt` that auto-splits past 100k characters
  - `.md` plus CN, and 404s that return Markdown with recovery links
  - an 18-option contextual menu
- Also: nine themes, server-rendered custom components, an OpenAPI 3.2 playground with streaming, and agent analytics.
- **Weak spots:** a big price jump (free has no AI; Pro is $450/mo plus credits) and a sameness that makes Mintlify sites recognizable at a glance.
- Sources: [pricing](https://www.mintlify.com/pricing), [changelog](https://www.mintlify.com/docs/changelog), [MCP](https://www.mintlify.com/docs/ai/model-context-protocol), [skill.md](https://www.mintlify.com/docs/ai/skillmd), [automations](https://www.mintlify.com/docs/automations/index), [traffic report](https://www.mintlify.com/blog/state-of-docs-traffic).

**documentation.ai.** "AI documentation that actually updates itself."
- **What's good:** a well-built automation catalogue:
  - Update from Code, API Sync from Code, Changelog, Self-Heal by User Feedback, Broken Link Audit, Style Guide and Navigation Audit
  - triggered by PR merge, a schedule or a manual run
  - output always lands on a branch for review
- A 32-tool **Authoring MCP** (`claude mcp add ... api.documentation.ai/mcp`) whose tools are gated by role.
- AI is included on the free tier, which undercuts Mintlify hard ($55 and $159 tiers).
- A free [Agent Readiness Score](https://documentation.ai/agent-score) built on the AFDocs spec.
- Migration from Mintlify, GitBook and others is packaged **as a Claude Code / Codex skill**, a clever pattern worth copying.
- Built on Next.js; `.md` and CN verified.
- Sources: [site](https://documentation.ai), [pricing](https://documentation.ai/pricing), [changelog](https://documentation.ai/docs/changelog), [authoring MCP](https://documentation.ai/docs/ai/authoring-mcp-server), [automations](https://documentation.ai/docs/ai/automations).

**GitBook.** The best editor for non-engineers.
- Change requests behave like PRs, with merge rules and diffs.
- Adaptive content shows different pages to different visitors (cookie, URL or feature flags).
- Versions and translations combine on one site.
- **Channels** let you @GitBook from Slack, GitHub, Linear or Jira; it opens a linked change request.
- No custom CSS or JS, so every GitBook site looks like GitBook.
- Sources: [pricing](https://www.gitbook.com/pricing), [changelog](https://gitbook.com/docs/changelog), [LLM-ready docs](https://gitbook.com/docs/getting-started/llm-ready-docs).

**ReadMe.** The most interesting agent idea of the quarter is **Restless** (October 2026): docs injected into API error responses so agents can recover, plus agent access to live API logs. ReadMe says agents are 72% of API-docs traffic.
- The GitHub AI Writer reads diffs and drafts updates on a review branch; it never publishes directly.
- Markdown responses carry `agentTools` frontmatter that points to a search endpoint.
- Sources: [Restless](https://readme.com/blog/restless), [AI-ready docs](https://docs.readme.com/main/docs/ai-ready-docs.md), [pricing](https://readme.com/pricing).

**Fern (Postman).** The most rigorous agent engineering:
- `<llms-only>` and `<llms-ignore>` tags.
- A `.md` miss suggests up to five similar pages.
- llms.txt for every navigation node.
- **Deliberately no llms-full.txt.**
- Sites host their own skills under `/.well-known/agent-skills/`.
- Authored the open-source [Agent Score](https://buildwithfern.com/agent-score), built on AFDocs.
- Sources: [markdown](https://buildwithfern.com/learn/docs/ai-features/markdown.md), [host skills](https://buildwithfern.com/learn/docs/ai-features/host-skills.md), [acquisition](https://buildwithfern.com/post/postman-acquires-fern).

**Scalar.** The MIT API reference (15.7k stars) has become the default reference UI almost everywhere.
- Agent Scalar uses just three MCP tools (summarize, search, execute) and fetches schemas just-in-time. Its benchmark: about 5.5k tokens vs about 20k for a conventional server.
- 13 CSS-variable themes.
- **For LaRecipe:** embed Scalar for API pages; don't compete with it.
- Sources: [pricing](https://scalar.com/pricing.md), [Agent Scalar](https://scalar.com/blog/posts/2026-03-05-agent-scalar/index.md).

**Redocly.** The **Reviewer** agent reviews every docs PR line by line: breaking spec changes, docs vs OpenAPI consistency, OWASP issues. Its MCP enforces RBAC.
- Its blog post ["llms.txt is still overhyped"](https://redocly.com/blog/llms-txt-still-overhyped.md) uses its own logs to show agents read per-page Markdown and MCP many times more often than llms.txt.
- Source: [AI features summer 2026](https://redocly.com/blog/ai-features-summer-2026.md).

**Archbee.** Shipped a **reader MCP for protected docs** (October 2026): readers verify by email, and access is re-checked on every call. This is the right model for private docs.
- Sources: [protected-docs connector](https://www.archbee.com/docs/ai-connector-for-protected-docs.md), [pricing](https://www.archbee.com/pricing).

**Document360.** An enterprise knowledge base with approval workflows and Eddy AI. Its one-way GitHub sync and quote-only pricing show where the old KB vendors sit: behind the curve on agents.
- Source: [MCP](https://docs.document360.com/docs/mcp.md).

**Theneo.** The Theneo Agent reads every PR and comments with what is outdated or missing, then drafts fixes. AI translation re-runs on publish.
- Sources: [agent in repo](https://docs.theneo.io/ai-in-theneo/ai-agent-in-your-repository.md), [pricing](https://www.theneo.io/pricing).

**Docs7 (Context7).** Launched September 2026. Reads Mintlify projects as they are, which makes migration its growth wedge.
- **Code Watch** opens a docs PR linked to each merged code PR.
- Built-in distribution to Context7's 100k+ users.
- Proof that **"import the leader's format"** works as a growth tactic.
- Source: [overview](https://context7.com/docs/docs7/overview.md).

**Also seen:**
- [Jamdesk](https://www.jamdesk.com): flat $29/mo with no AI meters, and its CLI is meant to be driven by agents.
- [Pageloop](https://www.pageloop.ai): finds stale docs from Slack, GitHub, Linear and tickets.
- [Ferndesk](https://ferndesk.com): AI-maintained help centres, $149/mo.

### Open-source frameworks

**Fumadocs.** The OSS benchmark for structure:
- A headless core, a UI package and a **pluggable content source** (`StaticSource`/`DynamicSource`, with Notion, Sanity and remote MDX adapters).
- A shadcn-style CLI that adds features (`feature llms`, `feature mcp`) and ejects layouts into your code (`customize`).
- `asMarkdown()` lets a component define its own agent output.
- Sources: [llms integration](https://fumadocs.dev/docs/integrations/llms), [source API](https://fumadocs.dev/docs/headless/source-api/source), [CLI](https://fumadocs.dev/docs/cli).

**Nextra.** Clean, but stalled: no npm release in 2026 and no llms.txt. It is a warning about bus factor.
- Source: [releases](https://github.com/shuding/nextra/releases).

**Docusaurus.** Still the best snapshot versioning and the largest plugin ecosystem (66.5k stars). It treats every agent surface as a plugin; the official stance is "do it as a plugin."
- Sources: [3.10](https://docusaurus.io/blog/releases/3.10), [v4 issue](https://github.com/facebook/docusaurus/issues/11719).

**Starlight.** The accessibility leader. Its component override map and `--sl-*` CSS variables are a clean customization contract. Agent features exist only as plugins (`starlight-llms-txt` is written by a core maintainer).
- Sources: [overrides](https://starlight.astro.build/guides/overriding-components/), [plugins](https://starlight.astro.build/resources/plugins/).

**VitePress.** The fastest dev loop. It added a native Ask AI button (Cmd+I) that opens a DocSearch side panel. Vite, Vue and Vitest docs use `vitepress-plugin-llms`.
- Source: [search](https://vitepress.dev/reference/default-theme-search).

**Rspress.** The strongest built-in agent support among the static generators:
- **SSG-MD** renders Markdown from the React tree, so Tabs serialize sensibly.
- CN is rewritten to the `.md` file.
- `injectLlmsHint` adds hidden text that points agents to llms.txt.
- WebMCP is on by default in 2.1.
- Ships authoring Agent Skills.
- Sources: [agent-friendly website](https://rspress.rs/blog/agent-friendly-website), [SSG-MD](https://rspress.rs/guide/basic/ssg-md.md).

**Zudoku.** An API portal built OpenAPI-first, with permission-aware search and shadcn theming (you can paste a tweakcn theme URL). Its `llms` config can include protected pages.
- Source: [llms](https://zudoku.dev/docs/configuration/llms.md).

**Docus.** The most complete out-of-the-box set:
- An AI assistant that answers through its own MCP server.
- `.md`, CN and AI user-agent detection.
- Skills at `/.well-known/skills`.
- Nuxt Studio for visual editing that commits to git.
- Sources: [assistant](https://docus.dev/raw/en/ai/assistant.md), [MCP](https://docus.dev/raw/en/ai/mcp.md).

**Vocs.** Agent-first:
- Components export a Markdown map.
- A `markdown-audit` check, plus a manifest of SHA-256 hashes.
- An MCP server that can also **search and read the source repo**.
- A guide to "find code examples that don't match the current source."
- This is the closest OSS work to "docs updated on push."
- Source: [agent support](https://vocs.dev/features/agent-support.md).

### AI-native maintainers

**DeepWiki.** Proved demand for code-derived explanations: 50k+ repos indexed, and a free MCP at `mcp.deepwiki.com/mcp` with three tools. But it is a separate artifact with no review step.
- Source: [docs](https://docs.devin.ai/work-with-devin/deepwiki-mcp).

**Google Code Wiki.** Makes "regenerate after every change" the baseline for code-explainer wikis.
- Source: [launch](https://developers.googleblog.com/introducing-code-wiki-accelerating-your-code-understanding/).

**Context7.** 63k stars. The `context7.json` repo config sets folders, rules and versions, and refresh frequency depends on popularity.
- Its "ContextCrush" incident (fixed in February 2026): library rules were injected into agent context unsanitised, which allowed credential exfiltration.
- **The lesson for LaRecipe:** any docs served to agents can carry prompt injection.
- Sources: [library owners](https://context7.com/docs/library-owners.md), [CVE](https://www.sentinelone.com/vulnerability-database/cve-2026-75130/).

**Promptless.** The best review workflow:
- Configurable triggers: PR open, approval, merge or push.
- Diff-style suggestions with citations.
- It posts a summary comment on the source PR.
- From $500/mo, free for eligible OSS.
- Source: [GitHub triggers](https://promptless.ai/docs/for-docs/connect/triggers/github-prs).

**Dosu.** Self-documenting PRs (suggested doc edits, accepted from GitHub). It also writes back: coding agents save what they learned during a task through its MCP.
- Source: [for agents](https://dosu.com/for-agents).

**Swimm.** Pivoted to legacy-code modernization in 2026. Selling code-coupled sync on its own did not survive once agents could regenerate docs.
- Source: [pivot post](https://swimm.io/blog/weve-stopped-asking-customers-to-bet-on-the-right-tool).

**Doc Holiday / DocuWriter.** Cheap, CI-driven "one docs PR per release" tools. Doc Holiday costs $100/mo on your own key, which shows bring-your-own-key pricing is accepted.
- Sources: [Doc Holiday](https://doc.holiday), [DocuWriter](https://www.docuwriter.ai).

**GitHub Agentic Workflows.** GitHub's own Daily Documentation Updater had 96% of its PRs merged (57/59).
- **For LaRecipe:** the free DIY baseline, and something LaRecipe should package rather than reinvent.
- Source: [gh-aw docs workflows](https://github.github.io/gh-aw/blog/2026-01-13-meet-the-workflows-documentation/).

### Laravel ecosystem

**LaraDocs.** The direct threat. Launched 2 June 2026, now at 21.8k monthly installs.
- Visibility rules filter navigation, search, sitemap, llms.txt, MCP and chat.
- MCP built on `laravel/mcp`, chat built on `laravel/ai`.
- Scout search, an OpenAPI driver via Scramble, 100% test coverage.
- **Gaps:** Blade only, local files only, no AI authoring, no per-page `.md`.
- Sources: [repo](https://github.com/PeteBishwhip/laradocs), [Packagist](https://packagist.org/packages/petebishwhip/laradocs).

**Vellum.** Tiny (5 stars), but it has the right instincts: components that print allow-listed config, env and route values, and per-page `access: auth` or a named gate.
- Source: [repo](https://github.com/VerburgtJimmy/vellum).

**Scribe / Scramble.** Own Laravel API reference (1.13M and 2.55M monthly installs). Scramble's Boost guideline and skill is the model for a "LaRecipe authoring skill." Integrate with both; compete with neither.
- Sources: [Scribe](https://github.com/knuckleswtf/scribe), [Scramble AI](https://scramble.dedoc.co/ai-assisted-development).

**laravel.com/docs.** Now Statamic plus Inertia React.
- One Markdown file per topic, one branch per version, and a `documentation.md` sidebar (the same convention as LaRecipe's `index.md`).
- `.md` endpoints, CN, and "Copy as markdown."
- No llms.txt and no Ask AI.
- Laravel's Cloud and Forge docs run on **Mintlify**: Laravel itself paid for agent surfaces that no Laravel package offered.
- Sources: [routing.md](https://laravel.com/framework/docs/routing.md), [Statamic post](https://statamic.com/blog/laravel-dot-com-runs-on-statamic), [Cloud llms.txt](https://laravel.com/cloud/docs/llms.txt).

**Laravel Boost.** 7.7M monthly installs.
- Packages can ship `resources/boost/skills/*/SKILL.md` and guidelines, and Boost auto-installs them.
- Its Search Docs tool queries a hosted, version-filtered docs API, but **third-party packages cannot add their own docs to it**.
- Source: [repo](https://github.com/laravel/boost).

**laravel/mcp v1 and laravel/ai v1.** These cover the hard parts:
- `laravel/mcp`: web and local servers, OAuth 2.1 and Sanctum auth, MCP Apps.
- `laravel/ai`: agents, tools, skills, embeddings on pgvector or MariaDB with `whereVectorSimilarTo()`, a `SimilaritySearch` RAG tool, and Vercel AI SDK stream protocol output that plugs straight into React `useChat`.
- Sources: [laravel/mcp](https://github.com/laravel/mcp), [laravel/ai](https://github.com/laravel/ai).

**Spatie docs.** A working reference for using a git repo as the content source:
- A GitHub webhook triggers the import.
- Branches are read through the Git Trees API.
- Each import goes into its own release directory, and a pointer switches over atomically only once every branch has imported.
- Plus `.md` on every page, CN, and a root llms.txt.
- Source: [PR #469](https://github.com/spatie/spatie.be/pull/469).

**Others:**
- Prezet (SQLite index, Phiki highlighting).
- HydePHP and Jigsaw (static).
- Filament Knowledge Base (docs inside a Filament panel).
- Statamic Sidecar: edits LaraDocs, Jigsaw, VitePress and HydePHP files, **but not LaRecipe**.

---

## Must have

What LaRecipe v3 needs to be credible next to this market in late 2026.

### Reader experience

- **Server-rendered HTML, with React for the interactive parts.**
  - Every page arrives as complete HTML (content, nav, TOC). React hydrates the interactive pieces: search, tabs, Ask AI, the playground.
  - *Who has it:* every static framework; Mintlify fixed its own component pop-in in October 2026.
  - *Why for LaRecipe:* a React SPA would hurt SEO and give agents empty pages. A runtime PHP renderer that sends complete HTML is LaRecipe's natural advantage over client-only React.
- **Cmd+K search with Ask AI in the same box.**
  - Instant fuzzy results with keyboard navigation and recent pages, plus an "Ask AI about '...'" row that hands off to the chat with the query filled in.
  - *Who has it:* GitBook ("Ask or search"), documentation.ai, Mintlify, VitePress, Docusaurus (DocSearch v4).
  - *Why for LaRecipe:* v2 search is broken on punctuation (#262, #377). Search is the first thing readers judge.
- **Page actions split button.**
  - "Copy page" as the main button. The menu holds View as Markdown, Open in ChatGPT, Open in Claude, Open in Cursor, Copy MCP URL and Add to Cursor/VS Code.
  - *Who has it:* Mintlify, GitBook, Fern, ReadMe, Fumadocs, Rspress, Docus, Zudoku, Nextra, Vocs, laravel.com ("Copy as markdown").
  - *Why for LaRecipe:* it is now expected, and visitors notice when it is missing. Keep it to about 6 options, not Mintlify's 18.
- **Navigation that never loses you.**
  - Sidebar that scrolls to the active page, TOC with scroll-spy (a popover on mobile), breadcrumbs, prev/next.
  - The version and locale switchers keep you on the same page.
  - *Who has it:* Fumadocs, Starlight, VitePress, Mintlify.
  - *Why for LaRecipe:* fixes open v2 bugs (#361 active item, #415 sidebar crash) and the subdirectory URL bug (#403).
- **A modern component set, server-rendered.**
  - Callouts, using GitHub's `> [!NOTE]` syntax as well as tags.
  - Tabs, code groups, steps, cards and columns, accordions, file tree, badges, Mermaid, and API field components.
  - Code blocks highlighted on the server with [Phiki](https://packagist.org/packages/phiki/phiki), with filename, line highlighting, diff and copy.
  - *Who has it:* everyone. documentation.ai lists 100+ components; Mintlify, Starlight and Fumadocs have the same core set.
  - *Why for LaRecipe:* the v2 Vue components (alerts, cards, badges) are the floor, and they have to come across without breaking existing content.
- **Ask AI with citations, scoped to the reader.**
  - A side panel (Cmd+I) that answers from the docs with linked citations and follow-up questions.
  - It respects the reader's version, locale and permissions, and says "I don't know" with a fallback contact.
  - *Who has it:* all the SaaS platforms, Docus (native), Fumadocs (CLI), LaraDocs.
  - *Why for LaRecipe:* it is built on `laravel/ai` with the owner's key and no credit meter. Mintlify charges 25 credits (about $0.25 at overage) per answer.
- **Accessibility, dark mode and RTL in core.**
  - WCAG 2.2 AA, `prefers-color-scheme`, full RTL, reduced motion, keyboard shortcuts with a help overlay.
  - *Who has it:* Starlight (the accessibility leader), Docusaurus, Fumadocs.
  - *Why for LaRecipe:* #370 (accessibility) is open. The archived `larecipe-dark-theme` and `rtl` repos literally say "v3 will include this out of the box."
- **Page feedback.**
  - Thumbs up/down with an optional comment. Stored in the DB and fed to analytics and automations.
  - *Who has it:* Mintlify, documentation.ai, GitBook, Nextra, Fern.
  - *Why for LaRecipe:* feedback is the input for self-healing docs (see Nice to have), and the `larecipe-feedback` add-on was archived.

### Authoring & AI

- **Authoring MCP server.**
  - Built on `laravel/mcp`. Tools to list the tree, read, search, write and move pages, edit navigation and config, upload media, diff, and publish or discard a draft.
  - Every write lands as a draft (DB source) or a branch (git source), never live.
  - *Who has it:* Mintlify Admin MCP, documentation.ai (32 tools), GitBook MCP, ReadMe MCP, Archbee, Document360.
  - *Why for LaRecipe:* "nobody writes docs by hand" only works if Claude Code or Codex can edit through a safe, role-gated interface. Laravel already gives you OAuth 2.1 and Sanctum for it.
- **Agent Skill and Boost guideline shipped in the package.**
  - `resources/boost/skills/larecipe/SKILL.md` and `resources/boost/guidelines/core.blade.php` teach any coding agent LaRecipe's syntax, components, frontmatter and nav format, and the `larecipe:check` loop.
  - *Who has it:* Scramble (Boost), Mintlify and GitBook (skill.md), Rspress (authoring skills), Fern (`fern-docs` skill).
  - *Why for LaRecipe:* Boost has 7.7M monthly installs and picks this up automatically. It is the cheapest way to put LaRecipe in front of every Laravel developer's agent.
- **Code-to-docs automation, drafts by default.**
  - On merge (or on a schedule), an agent reads the diff plus the current docs and opens a docs PR (git source) or creates a draft revision (DB source) linked to the code PR. A human approves it.
  - Ship three entry points:
    - a GitHub Action
    - `php artisan larecipe:sync --since=<sha>`
    - a signed webhook route
  - *Who has it:* Mintlify automations, documentation.ai, Docs7 Code Watch, Promptless, Dosu, ReadMe AI Writer, Theneo, GitBook Channels, GitHub Agentic Workflows (96% merge rate).
  - *Why for LaRecipe:* this is the maintainer's headline idea. Commercially it costs $200 to $500 a month, and no OSS framework ships it as a product.
- **Review queue with diffs.**
  - An in-app page that lists pending agent drafts, with a rendered diff, source PR link, approve/reject/edit, and an audit trail.
  - *Who has it:* Mintlify (review flow, suggesting mode), documentation.ai (branches and previews), Dosu (Review tab), Promptless (dashboard).
  - *Why for LaRecipe:* when agents write, humans review. The review screen becomes the main authoring screen.
- **A CLI that agents can drive.**
  - `larecipe:check` validates links, anchors, components, frontmatter, page size and nav orphans, with `--json` output.
  - Plus `larecipe:search`, `larecipe:read`, `larecipe:new`, `larecipe:publish`.
  - *Who has it:* Mintlify CLI (MDX checks, `mint test`), Redocly lint, Jamdesk CLI, Context7 `ctx7` + skill, `npx afdocs`.
  - *Why for LaRecipe:* agents iterate fastest against a deterministic validator. Artisan is already in every Laravel developer's agent loop.

### Agent surfaces

- **Markdown for every page.**
  - `/docs/{version}/{page}.md` plus `Accept: text/markdown` negotiation on the HTML URL, with `Vary: Accept` and a `Link: <...md>; rel="alternate"; type="text/markdown"` header.
  - A missing `.md` returns a 404 written in Markdown that suggests similar pages.
  - *Who has it:* Mintlify, GitBook, Fern, ReadMe, Scalar, Archbee, documentation.ai, Rspress, Docus, Fumadocs, laravel.com, Spatie.
  - *Why for LaRecipe:* direct `.md` URLs are 54.4% of agent requests on Mintlify (July 2026). Claude Code and Cursor send the Accept header. A runtime Laravel route makes both trivial, where static generators had to resort to edge rewrites.
- **llms.txt v2, scoped and capped.**
  - A root `/llms.txt` indexing versions and sections, per-version indexes, and `rel="describedby"` links (spec v2, August 2026).
  - Keep each index under 50k characters (AFDocs). Configurable "agent instructions" block. Optional `llms-full.txt` per version, capped.
  - *Who has it:* nearly everyone. Mintlify auto-splits into `/_llms/` past 100k characters; Fern skips llms-full on purpose.
  - *Why for LaRecipe:* AI crawlers barely read it, but coding agents use it to navigate. Mintlify measured about 90% fewer agent 404s with it. It is cheap to ship.
- **Docs MCP server (read).**
  - `/docs/mcp` with `search`, `read_page`, `list_pages` and `submit_feedback`.
  - Gate-aware, with Sanctum/OAuth for private docs, and a server card at `/.well-known/mcp/server-card.json`.
  - *Who has it:* Mintlify, GitBook, Fern, ReadMe, Scalar, Redocly, Docus, LaraDocs, DeepWiki.
  - *Why for LaRecipe:* Redocly sees many times more agent traffic through MCP than through llms.txt. On Mintlify, 56.6% of MCP calls are searches.
- **skill.md and agent-skills discovery.**
  - Auto-generate `/skill.md` and `/.well-known/agent-skills/index.json` (v0.2.0, with digests) from the docs, or serve hand-written skills from a `skills/` folder.
  - *Who has it:* Mintlify, Fern, Docus, GitBook, Docs7, Redocly.
  - *Why for LaRecipe:* this lets `npx skills add https://your-docs` work for any app documented with LaRecipe.
- **Components that render their own Markdown.**
  - Every component declares how it serializes for agents. Tabs become labelled sections; callouts become `> **Note:**` blocks.
  - *Who has it:* Fumadocs (`asMarkdown()`), Rspress (SSG-MD), Vocs (component markdown map).
  - *Why for LaRecipe:* converting HTML to Markdown mangles tabs (an AFDocs check). Designing this into the component API from day one is cheap; adding it later is not.
- **Discovery hints.**
  - A short blockquote at the top of every `.md` response pointing to `/llms.txt`, plus `Link` headers advertising llms.txt, MCP and skills.
  - *Who has it:* Mintlify (Link and `X-Llms-Txt` headers, injected index note), Rspress (`injectLlmsHint`).
  - *Why for LaRecipe:* agents don't find these resources on their own unless something points them there.
- **AFDocs grade A as a CI test.**
  - Run `npx afdocs check` against the demo site in LaRecipe's own CI.
  - *Who has it:* documentation.ai, Fern and ReadMe grade their customers' sites with it.
  - *Why for LaRecipe:* "A on the agent score out of the box, for free" is a claim nobody in PHP can make today.

### Content & sources

- **One source contract, three drivers.**
  - A `ContentSource` interface covering tree, read, write, revisions and change events, with `local` (any Flysystem disk), `git` and `database` drivers that have equal capabilities.
  - `php artisan larecipe:move-source local database` converts both ways.
  - *Who has it:* Fumadocs (`StaticSource`/`DynamicSource`), Astro loaders, Nuxt Content. The SaaS platforms hide the source entirely.
  - *Why for LaRecipe:* the maintainer's core vision, and nobody in Laravel offers it. Laravel's Storage disks give you S3 and local for free.
- **Git source with webhook-driven, atomic sync.**
  - Map branches or tags to versions.
  - Import through the Git Trees API into release directories, then switch over atomically.
  - A signed webhook triggers it; a scheduled check is the fallback.
  - *Who has it:* Spatie (in production), Mintlify, GitBook Git Sync.
  - *Why for LaRecipe:* "docs in a separate repo" is a stated requirement. Spatie's design is battle-tested in Laravel and needs no `git` binary on the server.
- **Plain Markdown as the canonical format.**
  - CommonMark + GFM, plus a small set of component tags that are parsed, never executed.
  - Every source round-trips to the same `.md` files.
  - *Who has it:* Mintlify and Fern (MDX in git), Jamdesk ("no vendor lock-in"). GitBook's block JSON is the counter-example.
  - *Why for LaRecipe:* agents write Markdown best, and portability is what makes switching sources safe.
- **Versioning that follows Laravel conventions.**
  - Versions from folders, branches or a DB column, a `default`/`latest` alias, a switcher that keeps you on the same page, and per-version search and llms.txt.
  - *Who has it:* Docusaurus (best), Rspress, Fumadocs, laravel/docs (branch per version).
  - *Why for LaRecipe:* versioning is a v2 headline feature, and Laravel developers already think in `12.x` / `13.x` branches.
- **Validated frontmatter and redirects.**
  - A schema for `title`, `description`, `order`, `hidden`, `badge`, `access`, `redirect_from`, `tags` and `updated_at`, with errors surfaced by `larecipe:check`.
  - *Who has it:* Starlight (type-safe schemas), LaraDocs, Mintlify.
  - *Why for LaRecipe:* agents need a strict contract, and redirects keep URLs stable (an AFDocs category).
- **Importers.**
  - From LaRecipe v2 (no changes needed), the laravel/docs format (`documentation.md`, `{{version}}`), Mintlify `docs.json`, and Docusaurus, VitePress and Starlight folders.
  - Package the migration as an agent skill as well as a command.
  - *Who has it:* documentation.ai (migrator as a Claude Code skill), Docs7 (reads Mintlify as-is), Jamdesk.
  - *Why for LaRecipe:* 3M existing installs must upgrade painlessly. Importing Mintlify is a proven way to win users.
- **OpenAPI pages, through integration.**
  - An `openapi` page type that reads Scramble or Scribe output (or any spec URL) and renders it with Scalar, including a try-it playground.
  - *Who has it:* Mintlify, Fern, ReadMe, Redocly, Zudoku, Fumadocs, LaraDocs (via Scramble).
  - *Why for LaRecipe:* most Laravel apps that publish docs expose APIs, and `larecipe-swagger` had 67.7k installs. Build the glue, not a renderer.
- **Multiple doc sets per app.**
  - `/docs`, `/api`, `/help` as separate sets, each with its own source, theme, access rules and agent surfaces.
  - *Who has it:* Mintlify (multi-repo), pxlrbt/laravel-docs (multiple instances).
  - *Why for LaRecipe:* the most-requested feature in issue #6 (47 comments).

### Customization & extensibility

- **Design tokens on CSS variables with Tailwind v4, shadcn-compatible.**
  - A theme is a token file (colours, radius, fonts, density) plus a layout choice. Dark mode is automatic. Pasting a shadcn/tweakcn theme should just work.
  - *Who has it:* Fumadocs (`--color-fd-*` + shadcn preset), Zudoku, Docus (Nuxt UI), Starlight (`--sl-*`).
  - *Why for LaRecipe:* the Laravel React starter kit is shadcn on Tailwind 4, so LaRecipe docs can match the host app's look with no extra work. This also fixes #359 (custom CSS has no effect).
- **Eject instead of forking.**
  - `php artisan larecipe:eject Sidebar` copies a component into the app as source you own. Everything else keeps updating.
  - *Who has it:* Fumadocs (`customize`), Docusaurus (swizzle), Rspress (`eject`), Starlight (override map).
  - *Why for LaRecipe:* replaces v2's theme-package sprawl, where most themes are now dead, with one supported escape hatch.
- **No build step to install, and no clash with the host's Inertia.**
  - Ship a prebuilt Vite/React bundle that is served or published the way Horizon and Telescope do it. Use LaRecipe's own root view and middleware, never the host's Inertia setup.
  - *Who has it:* Horizon, Telescope, Nova, Laravel Request Docs (React built with Astro, shipped prebuilt).
  - *Why for LaRecipe:* `composer require` followed by a working site is the v2 promise. Inertia's root view and asset version are global, so coupling to them breaks host apps.
- **Custom components with three renderers.**
  - Register a component with a React implementation, a server-side HTML fallback and a Markdown serializer. An optional host-app Vite entry adds custom React components.
  - *Who has it:* Mintlify (custom React components, server-rendered), Fumadocs, Rspress.
  - *Why for LaRecipe:* the v2 escape hatch was Blade-in-markdown. v3 needs a safe, typed replacement that also works for agents.
- **Driver-based extension points and Laravel events.**
  - Drivers for sources, search, AI providers, renderers and analytics sinks, plus events (`PagePublished`, `DraftCreated`, `SearchPerformed`, `AgentRequestServed`).
  - *Who has it:* Fumadocs (adapters), Docusaurus (plugins), LaraDocs (hooks such as `chatAuthorize`).
  - *Why for LaRecipe:* this is how Laravel developers expect to extend things, and it keeps core small.

### Operations

- **Search that works with zero config and scales with Scout.**
  - Default: a SQLite FTS5 (or database) index rebuilt when content changes, with typo tolerance. Optional Scout drivers for Meilisearch, Typesense or Algolia. Optional semantic search through `laravel/ai` embeddings.
  - *Who has it:* Pagefind/MiniSearch/Orama defaults in the OSS frameworks, LaraDocs (Scout), Docus (SQLite FTS5), Prezet (SQLite).
  - *Why for LaRecipe:* v2's internal search is broken (#377). Algolia-only would be a regression for self-hosters.
- **Gates everywhere.**
  - Laravel gates and policies on any page, section, version or doc set, enforced the same way across nav, search, sitemap, llms.txt, `.md`, MCP and Ask AI. A direct hit on a gated page returns 404.
  - *Who has it:* LaraDocs (visibility contract), Mintlify (`/authed/mcp`, group visibility), Archbee (protected-docs MCP), Redocly (RBAC MCP).
  - *Why for LaRecipe:* v2's auth gates are a beloved feature. The SaaS platforms charge Enterprise prices for this, and #287 (hide sidebar items by role) asks for exactly this.
- **Analytics that see agents.**
  - First-party DB analytics covering page views, search queries with zero-result rate, Ask AI questions and unanswered ones, MCP tool calls, feedback, and agent traffic split by user agent (`Claude-User`, `ChatGPT-User`, Cursor and others).
  - *Who has it:* Mintlify, GitBook, Fern, documentation.ai, Docs7, ReadMe (MCP metrics).
  - *Why for LaRecipe:* with agents at 66% of traffic, GA-only analytics see the minority. The data stays in the owner's database.
- **SEO and social.**
  - Canonical URLs, sitemap, JSON-LD (Organization, WebSite, BreadcrumbList), hreflang, generated OG images, robots.txt with `Content-Signal`.
  - *Who has it:* Mintlify (`mint og`, JSON-LD), documentation.ai, LaraDocs, Prezet (OG images).
  - *Why for LaRecipe:* v2 already sells "SEO/OG"; this keeps parity with 2026 expectations.
- **i18n structure.**
  - Locale folders or columns, per-locale navigation, search, llms.txt and MCP scope, `Accept-Language` routing, hreflang.
  - *Who has it:* Mintlify, GitBook, Docusaurus, Starlight, VitePress, Fumadocs, LaraDocs.
  - *Why for LaRecipe:* translation by agent (Nice to have) only works if the structure is already there.
- **HTTP correctness and caching.**
  - ETag and Last-Modified, cache invalidation driven by source events, Octane-safe, works under a subdirectory or a separate domain, and no soft 404s.
  - *Who has it:* AFDocs checks all of this; Mintlify, Spatie.
  - *Why for LaRecipe:* fixes #403 (subdirectory URLs) and makes the agent score achievable.

---

## Nice to have

Things that set LaRecipe apart but are not table stakes yet.

- **Visual editor, built for review first.**
  - Rendered editing with slash commands and the component picker, writing back to whichever source is active. Scope: approving and tweaking agent drafts, not a Notion clone.
  - *Who has it:* Mintlify, documentation.ai, GitBook, Fern Editor (every edit opens a PR), Nuxt Studio, Fumadocs Editor.
  - *Why for LaRecipe:* product and support people need to fix a typo without git. With a DB source this is easy.
- **Comments and suggestions on drafts.**
  - Inline threads with @mentions on draft revisions; agents can reply to and resolve them through MCP.
  - *Who has it:* Mintlify (agent replies to comments), documentation.ai, GitBook.
  - *Why for LaRecipe:* makes the review queue collaborative. Users map to the host app's users, so there are no extra accounts.
- **Self-healing docs from feedback and unanswered questions.**
  - A scheduled job groups thumbs-down feedback, zero-result searches and "I don't know" Ask AI answers into draft fixes.
  - *Who has it:* documentation.ai (Self-Heal), Mintlify (draft improvements from assistant conversations), Docs7 (Context7 Agent), Inkeep.
  - *Why for LaRecipe:* closes the loop with data LaRecipe already stores.
- **Translation by agent.**
  - A translation automation that re-runs when the source page changes, with a glossary, and opens drafts per locale.
  - *Who has it:* Mintlify, GitBook, Theneo, Archbee, Jamdesk.
  - *Why for LaRecipe:* `laravel/ai` makes this a queued job, and Laravel's community is very international.
- **`/ask` JSON endpoint.**
  - `GET /docs/ask?q=` returns ranked pages and snippets for agents that have no MCP client.
  - *Who has it:* Docs7, ReadMe (`agentSearch`).
  - *Why for LaRecipe:* a ten-line route that widens which agents can use the docs.
- **WebMCP (behind a flag).**
  - Register `search_docs` and `read_page` on `document.modelContext` for agents running in the browser.
  - *Who has it:* Mintlify, Rspress (on by default), Fumadocs, Docs7, GitBook, Redocly.
  - *Why for LaRecipe:* cheap to add, but it is still a Chrome origin trial with almost no consumers. Keep it opt-in.
- **Agent readiness panel.**
  - `php artisan larecipe:doctor` plus a dashboard card that grades the site against AFDocs and lists fixes.
  - *Who has it:* documentation.ai (Agent Readiness Score), Fern (Agent Score), ReadMe (Discoverability page).
  - *Why for LaRecipe:* turns compliance into a visible feature and a demo moment.
- **Snippets and variables.**
  - Reusable content blocks and `{{ variables }}` resolved at render time.
  - *Who has it:* Mintlify, documentation.ai, Archbee, LaraDocs.
  - *Why for LaRecipe:* cuts drift across versions. This becomes a unique feature when variables can read app config (see below).
- **Changelog page type with RSS.**
  - A dated entries component and feed that agents can draft from release tags.
  - *Who has it:* Mintlify (Update component), documentation.ai, Vellum, Theneo.
  - *Why for LaRecipe:* pairs naturally with code-to-docs automation ("draft changelog on tag").
- **Branch previews.**
  - `?ref=feature-x` (gated) renders a git branch or a draft set without deploying anything.
  - *Who has it:* Mintlify, documentation.ai, Scalar (preview per PR).
  - *Why for LaRecipe:* runtime rendering makes previews almost free compared with static hosts.
- **Narration and PDF/EPUB export.**
  - TTS read-aloud and offline exports.
  - *Who has it:* Mintlify (narration, PDF on Enterprise), Fumadocs (PDF/EPUB), documentation.ai (PDF).
  - *Why for LaRecipe:* `laravel/ai` covers TTS. Useful for enterprise buyers; low priority.

---

## Where LaRecipe can be unique

These are opportunities the SaaS platforms structurally cannot match and the JS frameworks do not try.

1. **Docs that read the app they document.**
   - Ship "live reference" components that resolve against the running app, so values can never drift:
     - `<Route name="orders.store" />` renders method, URI, middleware and the FormRequest rules.
     - `<Config key="services.stripe" />` shows the documented keys with defaults (allow-listed, never secrets).
     - `<Artisan command="orders:prune" />` shows the signature and options.
     - `<Model name="Order" />` shows columns, casts and relations.
     - `<Event>` and `<Env>` follow the same pattern.
   - `larecipe:check` fails CI when a referenced route, config key or command disappears.
   - Agents write `<Route name=...>` instead of pasting stale values, which removes the main cause of rotting docs.
   - Vellum hints at this; nobody does it properly.

2. **A docs agent that runs inside the app, not just in the repo.**
   - Mintlify, Promptless and Docs7 agents see repo text only.
   - `php artisan larecipe:sync` runs inside the Laravel container (CI, a Laravel Cloud deploy hook or a queue worker) with Boost-level introspection: DB schema, `route:list`, config, installed package versions.
   - It writes drafts grounded in what the app actually does.
   - It uses `laravel/ai` agents with human-in-the-loop tool approval, so the "update docs on push" pipeline is a Laravel job you can test, queue and monitor in Horizon or Nightwatch.

3. **Gates are the permission model, so access control costs nothing extra.**
   - One policy decides what a user sees in the HTML, `.md`, search, llms.txt, MCP and Ask AI.
   - Gate tags (a `<Can ability="view-billing">` block) give per-plan or per-role content, which GitBook calls "adaptive content" and charges $249/site/mo for.
   - Customers authenticate to the docs MCP with the Sanctum tokens they already have for your API.
   - That makes private customer docs, partner portals and internal runbooks a config change, not a sales call.

4. **Self-hosted AI with no meters, down to air-gapped.**
   - Bring any `laravel/ai` provider (OpenAI, Anthropic, Gemini, Bedrock, Ollama), store embeddings in your own Postgres or MariaDB, keep conversations in your own DB.
   - Mintlify meters 25 credits per answer and 250 per automation run on top of $450/mo; LaRecipe costs only your tokens.
   - With Ollama, regulated companies get Ask AI and authoring agents with zero data leaving the network, which no SaaS listed here offers below Enterprise "self-hosting available" pricing.

5. **The database source unlocks product-generated and per-tenant docs.**
   - Docs stored as Eloquent models (`Page`, `Revision`, `DocSet`) give you:
     - revisions and scheduled publishing
     - multi-tenant docs (each customer gets a branded set with their own content and gates)
     - pages generated by the product itself (a per-customer integration guide with their webhook URL and plan limits filled in)
     - model events that refresh search, embeddings and caches
   - An optional Filament resource can manage it all.
   - File-based tools cannot do this at all.

6. **Your docs become your coding agent's context.**
   - Boost's Search Docs cannot index third-party or app docs. LaRecipe can fill that gap:
     - a local MCP server (`Mcp::local`) plus a Boost guideline, so Claude Code working on the app can search the app's own internal docs (architecture notes, runbooks, domain glossary)
     - `php artisan larecipe:agents-md` writes a compressed docs index into AGENTS.md/CLAUDE.md
   - Vercel's evals scored an 8KB AGENTS.md index at 100%, against 53 to 79% for skills.
   - Add Dosu-style write-back: when the agent finishes a task, it drafts the doc update in the same session.

7. **In-app contextual help from the same content.**
   - A drop-in React/Blade widget (`<x-larecipe::help page="billing.invoices" />`) opens the relevant doc, or an Ask AI scoped to it, inside the host product.
   - It shares the user's session, has no CORS to deal with, and respects gates.
   - Mintlify's widget, GitBook's embedded Assistant and documentation.ai's embedded docs are paid features on a separate domain. LaRecipe lives in the same app, so this is native.

8. **One command gives any Laravel package a full docs site.**
   - Point LaRecipe at any repo that follows the laravel/docs or Spatie layout (`/docs`, branch per version, `documentation.md` or `index.md`) and get versioned HTML, `.md`, llms.txt, MCP and skills.
   - Ship a "package author" preset plus a Boost skill so every package can also contribute agent guidance.
   - Statamic Sidecar skipped LaRecipe; this wins the package ecosystem back.

9. **Open-source reference implementation of the agent-docs standards for PHP.**
   - Be the first PHP project to pass AFDocs at grade A, implement llms.txt v2 `Link` relations, MCP server cards and the agent-skills index, with conformance tests in CI.
   - Publish the source drivers and Markdown negotiation as standalone packages, for example `larecipe/agent-surface` middleware that any Laravel site (marketing pages, blogs) can use.
   - That spreads the brand well beyond docs.

---

## Anti-patterns to avoid

- **AI paywalls and credit meters.**
  - Mintlify's free tier has no AI and Pro is $450/mo plus credits; Archbee sells token packs; ReadMe's Ask AI costs $150/mo extra.
  - LaRecipe should never meter or phone home. Bring your own key, no telemetry, no "LaRecipe Cloud required."
- **The generic AI-docs look.**
  - Purple-to-blue gradients, sparkle icons on everything, a floating chat bubble covering content, and the same Mintlify/Fumadocs layout everyone else ships.
  - "Outstanding unique UI" means a recognizable identity (typography, rhythm, a signature interaction) with restrained, fast motion. AI should appear where it is useful, not as decoration.
- **Menus with 18 options.**
  - Mintlify's contextual menu offers ChatGPT, Claude, Perplexity, Grok, AI Studio, Devin, Devin Desktop and more.
  - Ship about six good options and a config hook for the rest.
- **Executing content.**
  - Blade-in-markdown was fine for trusted local files. With DB sources and agent-written content it becomes remote code execution.
  - Content is data: components are parsed from an allow-list, and Blade evaluation is opt-in for local, trusted sources only.
  - Also sanitise anything served to agents (the Context7 "ContextCrush" lesson).
- **Client-only rendering.**
  - A React SPA with empty HTML fails SEO, fails agents that don't run JS, and makes components pop in.
  - Mintlify had to retrofit server-rendered components in October 2026. Start with server-rendered HTML.
- **Coupling to the host's frontend stack.**
  - Don't require the host app to run Node, adopt Inertia, or share its root view, Tailwind config or Vite build.
  - Filament's "custom theme with `@source` pointing into vendor" step is the friction to avoid.
- **Lock-in formats.**
  - GitBook's block JSON, or DB-only content with no export.
  - Every LaRecipe source must round-trip to plain Markdown files, or the "switch sources any time" promise is false.
- **Agents publishing without review by default.**
  - Mintlify offers `automerge`; DocuWriter has auto-apply rules.
  - Default to drafts and PRs, make auto-publish an explicit per-automation opt-in, and keep an audit log of who (human or agent) changed what.
- **Whole-site `llms-full.txt` dumps.**
  - They overflow agent context, and Fern dropped them on purpose.
  - Scope them per version and section, cap them, and treat per-page `.md` plus MCP as the primary agent surfaces.
- **Serving agents different content from humans.**
  - User-agent sniffing that returns different facts is cloaking, and AFDocs checks Markdown/HTML parity.
  - Same content, different encoding.
- **Plugin and theme sprawl.**
  - v2's ecosystem (dark theme, RTL, swagger, feedback, emojify) is mostly archived or dead.
  - Put the essentials in core, keep the extension points few and typed, and offer eject for the rest.
- **Chasing every framework and every draft standard.**
  - Fumadocs supports five meta-frameworks. LaRecipe should be Laravel-only and great at it.
  - Keep WebMCP and other unratified specs behind flags so you can follow them without betting on them.
- **Shipping a stalled rewrite.**
  - The 2022-24 `3.x` branch (Vue 3) stalled, and Nextra shows how fast a framework loses trust without releases.
  - Ship a narrow v3.0 (renderer, sources, agent surfaces, React UI) quickly, then add AI authoring in point releases.

---

## Sources

**Mintlify**
- https://mintlify.com
- https://www.mintlify.com/pricing
- https://www.mintlify.com/docs/changelog
- https://www.mintlify.com/docs/ai/llmstxt
- https://www.mintlify.com/docs/ai/markdown-export
- https://www.mintlify.com/docs/ai/model-context-protocol
- https://www.mintlify.com/docs/ai/mintlify-mcp
- https://www.mintlify.com/docs/ai/skillmd
- https://www.mintlify.com/docs/ai/contextual-menu
- https://www.mintlify.com/docs/ai/assistant
- https://www.mintlify.com/docs/agent
- https://www.mintlify.com/docs/automations/index
- https://www.mintlify.com/docs/customize/themes
- https://www.mintlify.com/blog/series-b
- https://www.mintlify.com/blog/state-of-docs-traffic
- https://www.mintlify.com/blog/state-of-ai
- https://www.mintlify.com/blog/agent-analytics
- https://www.mintlify.com/blog/docs-on-autopilot
- https://www.finsmes.com/2026/04/mintlify-raises-45m-in-series-b-funding-at-500m-valuation.html
- https://ferndesk.com/blog/mintlify-review

**documentation.ai**
- https://documentation.ai
- https://documentation.ai/pricing
- https://documentation.ai/docs
- https://documentation.ai/docs/llms.txt
- https://documentation.ai/docs/changelog
- https://documentation.ai/docs/ai/automations
- https://documentation.ai/docs/ai/authoring-mcp-server
- https://documentation.ai/docs/ai/ai-assistant
- https://documentation.ai/docs/components/components
- https://documentation.ai/agent-score

**Other hosted platforms**
- https://www.gitbook.com/pricing
- https://www.gitbook.com/
- https://gitbook.com/docs/changelog
- https://gitbook.com/docs/changelog/2025-product-updates
- https://www.gitbook.com/blog/introducing-docs-agent
- https://gitbook.com/docs/getting-started/llm-ready-docs
- https://gitbook.com/docs/gitbook-agent/channels.md
- https://gitbook.com/docs/docs-as-code/ai-coding-assistants-and-skillmd.md
- https://gitbook.com/docs/ai-for-your-readers/mcp-servers-for-published-docs
- https://www.gitbook.com/blog/new-in-gitbook-february-2026
- https://www.gitbook.com/blog/ai-docs-data-april-2026
- https://readme.com/pricing
- https://readme.com/blog/restless
- https://readme.com/blog/may-launch
- https://docs.readme.com/main/docs/ai-ready-docs.md
- https://docs.readme.com/main/docs/mcp.md
- https://docs.readme.com/main/docs/github-ai-writer
- https://buildwithfern.com/pricing
- https://buildwithfern.com/post/postman-acquires-fern
- https://buildwithfern.com/learn/docs/ai-features/markdown.md
- https://buildwithfern.com/learn/docs/ai-features/llms-txt
- https://buildwithfern.com/learn/docs/ai-features/mcp-server.md
- https://buildwithfern.com/learn/docs/ai-features/host-skills.md
- https://buildwithfern.com/learn/docs/ai-features/fern-agent
- https://buildwithfern.com/learn/docs/changelog
- https://buildwithfern.com/agent-score
- https://scalar.com/pricing.md
- https://scalar.com/products/docs/index.md
- https://scalar.com/products/docs/configuration/ask-ai/index.md
- https://scalar.com/blog/posts/2026-03-05-agent-scalar/index.md
- https://scalar.com/blog/posts/2026-09-29-release-notes-2026-09-26/index.md
- https://redocly.com/pricing
- https://redocly.com/blog/ai-features-summer-2026.md
- https://redocly.com/docs/realm/ai-ready.md
- https://redocly.com/docs/realm/customization/agent-skills.md
- https://redocly.com/blog/redoc-3-whats-new.md
- https://redocly.com/blog/llms-txt-still-overhyped.md
- https://www.archbee.com/pricing
- https://www.archbee.com/docs/ai-connector-for-protected-docs.md
- https://www.archbee.com/docs/github-2-way-sync.md
- https://www.archbee.com/docs/auto-translation-with-ai.md
- https://document360.com/pricing/
- https://docs.document360.com/docs/mcp.md
- https://docs.document360.com/docs/ai-writing-agent.md
- https://docs.document360.com/docs/llms-txt.md
- https://www.theneo.io/pricing
- https://docs.theneo.io/ai-in-theneo/ai-agent-in-your-repository.md
- https://docs.theneo.io/ai-in-theneo/generate-mcp-server.md
- https://docs.theneo.io/ai-in-theneo/ai-markdown-export.md
- https://context7.com/docs/docs7/overview.md
- https://context7.com/docs/docs7/agent-readiness.md
- https://context7.com/docs/docs7/agent.md
- https://context7.com/migrate-from-mintlify
- https://www.jamdesk.com
- https://www.pageloop.ai
- https://ferndesk.com
- https://ferndesk.com/blog/best-mintlify-alternatives

**Open-source frameworks**
- https://github.com/fuma-nama/fumadocs
- https://fumadocs.dev/blog/v16-17
- https://fumadocs.dev/docs/integrations/llms
- https://fumadocs.dev/docs/headless/source-api/source
- https://fumadocs.dev/docs/cli
- https://fumadocs.dev/docs/ui/theme
- https://fumadocs.dev/docs/editor
- https://github.com/shuding/nextra
- https://the-guild.dev/blog/nextra-4
- https://github.com/facebook/docusaurus
- https://docusaurus.io/blog/releases/3.10
- https://docusaurus.io/blog/releases/3.9
- https://github.com/facebook/docusaurus/issues/11719
- https://docusaurus.io/docs/swizzling
- https://github.com/withastro/starlight
- https://starlight.astro.build/guides/overriding-components/
- https://starlight.astro.build/resources/plugins/
- https://github.com/delucis/starlight-llms-txt
- https://github.com/vuejs/vitepress
- https://vitepress.dev/reference/default-theme-search
- https://github.com/okineadev/vitepress-plugin-llms
- https://rspress.rs/blog/rspress-v2
- https://rspress.rs/blog/agent-friendly-website
- https://rspress.rs/guide/basic/ssg-md.md
- https://rspress.rs/guide/start/ai.md
- https://github.com/zuplo/zudoku
- https://zudoku.dev/docs/configuration/llms.md
- https://zudoku.dev/docs/configuration/ai-assistants.md
- https://github.com/nuxt-content/docus
- https://docus.dev/raw/en/ai/assistant.md
- https://docus.dev/raw/en/ai/mcp.md
- https://docus.dev/raw/en/ai/skills.md
- https://docus.dev/raw/en/ai/llms.md
- https://vocs.dev/features/agent-support.md
- https://vocs.dev/features/mcp-server.md
- https://vocs.dev/introduction/writing-docs-with-ai.md
- https://zensical.org/

**AI-native tools**
- https://docs.devin.ai/work-with-devin/deepwiki
- https://docs.devin.ai/work-with-devin/deepwiki-mcp
- https://cognition.com/blog/deepwiki
- https://github.com/AsyncFuncAI/deepwiki-open
- https://developers.googleblog.com/introducing-code-wiki-accelerating-your-code-understanding/
- https://github.com/upstash/context7
- https://context7.com/docs/library-owners.md
- https://context7.com/docs/plans-pricing
- https://www.sentinelone.com/vulnerability-database/cve-2026-75130/
- https://promptless.ai
- https://promptless.ai/pricing
- https://promptless.ai/docs/for-docs/connect/triggers/github-prs
- https://dosu.dev
- https://dosu.dev/pricing
- https://dosu.dev/blog/august-2025-dosu-drop
- https://dosu.com/for-agents
- https://swimm.io/blog/swimm-2-0-the-understanding-platform-for-ai-modernization
- https://swimm.io/blog/weve-stopped-asking-customers-to-bet-on-the-right-tool
- https://doc.holiday
- https://www.docuwriter.ai
- https://github.github.io/gh-aw/blog/2026-01-13-meet-the-workflows-documentation/
- https://docs.coderabbit.ai/finishing-touches/docstrings
- https://www.kapa.ai
- https://inkeep.com

**Standards and studies**
- https://llmstxt.org
- https://llmstxt.org/changes.html
- https://agentdocsspec.com/spec/
- https://afdocs.dev/checks/
- https://github.com/agent-ecosystem/afdocs
- https://dacharycarey.com/2026/02/18/agent-friendly-docs/
- https://www.ezy.ai/research/do-ai-bots-read-llms-txt
- https://www.searchenginejournal.com/googles-mueller-says-llms-txt-cant-help-llms-differentiate-sites/579304/
- https://developers.cloudflare.com/changelog/2026-02-12-markdown-for-agents
- https://vercel.com/blog/making-agent-friendly-pages-with-content-negotiation
- https://www.checklyhq.com/blog/state-of-ai-agent-content-negotation/
- https://vercel.com/blog/agents-md-outperforms-skills-in-our-agent-evals
- https://agentskills.io/specification
- https://github.com/cloudflare/agent-skills-discovery-rfc
- https://agents.md
- https://github.com/GoogleCloudPlatform/open-knowledge-format
- https://www.spronta.com/blog/state-of-webmcp-july-2026/
- https://cursor.com/docs/context/mcp/install-links
- https://www.anthropic.com/engineering/writing-tools-for-agents

**Laravel ecosystem**
- https://github.com/saleem-hadad/larecipe
- https://packagist.org/packages/binarytorch/larecipe
- https://github.com/PeteBishwhip/laradocs
- https://laradocs.dev/
- https://packagist.org/packages/petebishwhip/laradocs
- https://laravel-news.com/version-controlled-documentation-inside-your-laravel-app-with-laradocs
- https://github.com/VerburgtJimmy/vellum
- https://packagist.org/packages/pxlrbt/laravel-docs
- https://github.com/knuckleswtf/scribe
- https://scribe.knuckles.wtf/laravel/
- https://github.com/dedoc/scramble
- https://scramble.dedoc.co/ai-assisted-development
- https://github.com/rakutentech/laravel-request-docs
- https://github.com/prezet/prezet
- https://hydephp.com/
- https://github.com/tighten/jigsaw
- https://github.com/GuavaCZ/filament-knowledge-base
- https://statamic.com/blog/sidecar
- https://statamic.com/blog/laravel-dot-com-runs-on-statamic
- https://github.com/laravel/docs
- https://laravel.com/framework/docs/routing.md
- https://laravel.com/cloud/docs/llms.txt
- https://laravel.com/cloud/docs/.well-known/mcp/server-card.json
- https://laravel-news.com/laravel-cloud-adds-markdown-for-agents-to-serve-ai-friendly-content
- https://github.com/laravel/boost
- https://github.com/laravel/boost/blob/main/src/Mcp/Tools/SearchDocs.php
- https://github.com/laravel/mcp
- https://github.com/laravel/ai
- https://laravel.com/blog/everything-we-announced-at-laracon-us-2026
- https://laravel-news.com/inertia-3-0-0
- https://github.com/laravel/react-starter-kit
- https://github.com/laravel/horizon
- https://spatie.be/llms.txt
- https://github.com/spatie/spatie.be/pull/469
- https://packagist.org/packages/phiki/phiki
