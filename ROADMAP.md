# LaRecipe v3 · Roadmap

> When each part of v3 ships and what "done" means for it. `ARCHITECTURE.md` defines what we build; this file defines the order, the releases and the exit criteria.

Status: draft, 2026-10-17, revised the same day with the maintainer's review decisions. No calendar dates: each phase ends when its exit criteria pass in CI, not on a deadline.

---

## Sequencing rules

**The Agent-Surfaces-First Rule.** Agent read surfaces (`.md`, `llms.txt`, `skill.md`, MCP read tools) ship in the first alpha, together with the renderer. They are cheap once the AST exists, and they are the product's headline, not a later add-on.

**The Showcase Rule.** `larecipe-docs` is rebuilt on every phase's build. If the docs site does not use a feature, the feature is not done. The site is the proof of the value proposition, and its landing page is the docs themselves.

**The Exit-Criteria Rule.** A phase closes when its exit criteria pass, not when its task list is empty. Unfinished items move to the next phase explicitly.

**The Shippable-Slice Rule.** Every phase ends with a public tagged release someone can `composer require` and use, announced where Laravel developers read. No phase depends on the next one to be useful. Between phases, smaller `alpha.N` and `beta.N` tags ship as soon as a slice is green; a quiet rewrite is how the 2022-2024 `3.x` branch died.

---

## Release map

| Phase | Release | Headline |
| --- | --- | --- |
| 0 · Strategy and design | none | Positioning, brand, design system, architecture, this roadmap |
| 1 · Core and reading experience | `3.0.0-alpha.1` | Markdown in your repo becomes a fast React docs site that agents can read, with your own React components |
| 2 · AI and agent workflows | `3.0.0-beta.1` | Your coding agent updates the docs before it pushes, in the same PR as the code; onboarding writes your first docs; readers can ask the docs |
| 3 · Database source and languages | `3.0.0` | The database source with an admin UI and docs migrations, switch any time, multi-language docs, static export, hardening |
| 4 · Reach | `3.1`, `3.2`, ... | Standalone CLI, OpenAPI pages |
| Parallel · larecipe-docs | per phase | The v3 docs site, rebuilt from scratch on v3; the docs are the landing page |
| Maintenance · 2.x | patch releases | Security fixes until 3.0 stable, then deprecation |

---

## Phase 0 · Strategy and design (now)

**Deliverables**

- `PRODUCT.md`: positioning, audiences, promise, voice, what LaRecipe is not.
- Brand directions: two or three visual directions as a comparison page; the maintainer picks one.
- `DESIGN.md` + `DESIGN.json`: tokens, typography, components, named rules (Nashra format), for the chosen direction.
- `ART_DIRECTION.md`: what LaRecipe feels like, its signature moves, what it refuses to be.
- `ARCHITECTURE.md`: the technical RFC.
- `ROADMAP.md`: this file.

**Exit criteria**

- [x] Brand direction chosen and recorded in the decision log (A, The Pass, with Nashra type).
- [x] Composer vendor and namespace decided (`larecipe/larecipe`, `LaRecipe\`).
- [x] Architecture review feedback applied (sources, agent workflow, review modes, docs migrations, search, custom components, onboarding, domains; agent instructions and a pre-push hook instead of a CI agent; no built-in traffic insight; no importers or in-app help; 2026-10-17).
- [ ] Remaining open questions in `ARCHITECTURE.md` §24 accepted or changed by the maintainer.
- [ ] `PRODUCT.md`, `DESIGN.md` and `ART_DIRECTION.md` reviewed by the maintainer.

---

## Phase 1 · Core and reading experience → `3.0.0-alpha.1`

The engine, the repository source, the React reading experience, custom components and the agent read surfaces.

**Deliverables**

- *Foundation.* v3 branch restructured (v2 code removed; history keeps it), new `composer.json` at the PHP 8.3 / Laravel 12.62+ floor, the `src/Domains/` layout with domain service providers, pnpm workspace (`ui/`, `packages/ui`, `packages/vite`), CI with Pest, Larastan, Pint, Vitest, Playwright and the arch tests (allowed-dependency matrix, Portable-Domain Rule). Testbench workbench app with fixture docs.
- *Content.* Content model (DocSet, Version, Locale, Page, Provenance), `larecipe.json` and `navigation.json` loaders with JSON Schemas, zero-config navigation from folders and frontmatter `order`, frontmatter schema, `larecipe:navigation:freeze`.
- *Repository source.* The `repository` driver (`docs/` by default), git provenance compiled by `larecipe:optimize`, the shared source contract suite.
- *Doc sets.* `sets` config with a default `docs` set, `SetRegistry`, per-set route groups, one driver per set, access policy, theme and agent surfaces; a set can mount at the domain root. The data model, routing and agent surfaces are multi-set from day one; a UI set switcher comes later.
- *Markdown.* commonmark environment: GFM, frontmatter, clean heading ids, attributes allowlist, footnotes, GitHub alerts, `:::` directives, PascalCase component tags, relative `.md` link resolution, `{{version}}`. Phiki dual-theme highlighting with titles, line highlights and diffs.
- *Components.* `Callout`, `Steps`, `Tabs`, `CodeGroup`, `Card`/`CardGroup`, `Columns`, `Badge`, `FileTree`, `Frame`, `Accordion`, `Video` (with labelled placeholders), each with server fallback HTML, agent Markdown and a React island where needed; props validation.
- *Rendering.* `HtmlRenderer`, `AgentMarkdownRenderer`, TOC extraction, link collection, content-addressed cache with per-request holes (CSRF), `larecipe:optimize` wired into `php artisan optimize`.
- *HTTP.* Service provider, config, per-set routes, content negotiation (`.md`, `Accept: text/markdown`, page JSON) with `Vary`, `Link` (Markdown, llms.txt, MCP, skill), `ETag` and `Last-Modified` headers, Markdown 404s with up to five suggestions, Blade shell with full prerender (including insert-point fallbacks), fingerprinted asset route, `larecipe:publish --assets`, unversioned URLs for single-version sets, redirects, sitemap, `viewLarecipe` gate through one `PageAccess` policy.
- *UI.* React 19 shell with named regions, article adoption and islands, client-side navigation with prefetch, command palette, version switcher that keeps the reader on the same page, light and dark modes from tokens, page actions split button (copy page as Markdown; view `.md`, open in Claude, ChatGPT or Cursor, copy MCP snippet, edit on GitHub), previous/next, TOC with scroll spy, feedback widget (emits `FeedbackSubmitted` and an analytics-integration event; nothing stored), analytics integrations from `larecipe.json` (GA4, Plausible, Fathom, PostHog, custom script), keyboard shortcuts, WCAG 2.2 AA baseline. Visual design from `DESIGN.md`.
- *Custom React components (extension basics).* `@larecipe/vite`, `defineExtension`, `window.LaRecipe.register`, import-map React sharing (one React instance), `LaRecipe::component()` PHP definitions (props, fallback, Markdown). Inline use as Markdown tags and site-wide use at insert points (`header.actions`, `sidebar.before`, `sidebar.after`, `article.before`, `article.after`, `toc.after`, `footer`) from `larecipe.json` or the extension, with the frontmatter opt-out. Runtime helpers in `@larecipe/ui`: `useLaRecipe()`, `request()` (same-origin, session and CSRF), theme tokens as CSS variables, primitives (`Button`, `Input`, `Field`, `Callout`, `Badge`, `Card`, `Kbd`, `Loader`, `Icons`), and `LaRecipe::shareUser()`.
- *Search.* Search records per set, version, locale and audience; the client index served as fingerprinted JSON and searched with MiniSearch (fuzzy and prefix, field boosts), loaded lazily; audience variants so private records never reach the browser; the PHP server ranker for MCP and Markdown 404 suggestions; the `server` driver; a tokenizer that handles code identifiers and punctuation (v2 #262, #377).
- *Agent read surfaces.* `llms.txt` (root and per version, capped at 50k characters with automatic splitting) and per-version capped `llms-full.txt`; discovery hint at the top of every `.md`; auto-generated `skill.md` per set, hand-written skills from `skills/`, and `/.well-known/agent-skills/index.json`; MCP server (one stdio server, one web endpoint per set) with `search_docs`, `read_page`, `list_pages`, `validate_docs`, `submit_feedback`, resource `docs://{set}/{version}/{path}`.
- *Commands.* `larecipe:install` (driver, scaffold with a real Introduction page, agent detection, MCP config for detected agents; onboarding comes in Phase 2), `larecipe:validate --json`, `larecipe:make:page`, `larecipe:make:version`, `larecipe:navigation:freeze`, `larecipe:mcp`.

**Exit criteria**

- [ ] `composer require larecipe/larecipe` plus `php artisan larecipe:install` gives a working docs site in under two minutes with no Node and no extra build step.
- [ ] Playwright suite passes with JavaScript disabled: every page readable, every link works, every component's fallback is legible.
- [ ] No layout shift when React takes over (CLS < 0.02 in Lighthouse CI); performance budgets in `ARCHITECTURE.md` §21 met, including the search index budget.
- [ ] `curl -H 'Accept: text/markdown'` and `.md` URLs return agent Markdown for every fixture page; a missing `.md` returns a Markdown 404 with suggestions; `llms.txt` validates against the spec and stays under 50k characters.
- [ ] `npx afdocs check` grades the workbench site A, as a required CI job.
- [ ] The workbench app serves two doc sets (`/docs` public, `/handbook` behind auth), each with its own `llms.txt`, `skill.md`, MCP endpoint and search index variants, and nothing from `/handbook` reaches a guest through any surface, including the search index.
- [ ] Claude Code connected through `php artisan larecipe:mcp` answers questions about the fixture docs using `search_docs` and `read_page`.
- [ ] Renderer snapshot suite covers every component in both HTML and agent Markdown.
- [ ] larecipe-docs renders the `Subscribe` component built with `@larecipe/vite` with a single React instance, inline on the Introduction page and at the `article.after` insert point; it works with JavaScript disabled through the fallback form; and it appears in the Introduction page's agent Markdown as one line of plain text.
- [ ] The arch tests pass: no domain reaches into another domain's internals, and portable domains reference Laravel only in adapter folders.
- [ ] The larecipe-docs skeleton runs on `3.0.0-alpha.1`, with the docs as the landing page.

---

## Phase 2 · AI and agent workflows → `3.0.0-beta.1`

The owner's coding agent writes and maintains the docs in the same PR as the code; onboarding writes the first docs; readers can ask the docs; v2 users can migrate.

**Deliverables**

- *Drift core (L0).* `sources:` frontmatter, `larecipe:affected` (json, github, text formats; `--uncommitted`; stale-since detection), `larecipe:sources:suggest`, snippet includes with validation of missing regions.
- *Instructions in the repo (L1).* The marker-delimited LaRecipe block in `AGENTS.md` (generated from the driver, docs path, default branch and review mode; `@AGENTS.md` in `CLAUDE.md` with consent; the Boost guideline instead when Boost is installed) telling every AI session to run `larecipe:affected`, update the pages with `larecipe-update-docs` (or write a docs migration), run `larecipe:validate` and push the docs with the code. The four skills (`larecipe-write-docs` with the onboarding workflow and audience presets in its references, `larecipe-update-docs`, `larecipe-audit-docs`, `larecipe-upgrade-v2`), with references generated from the component registry and schemas; installer writes `.claude/skills` and `.agents/skills` when Boost is absent; `larecipe:agents` re-sync; `larecipe:agents-md` (the docs index in the same block); Claude Code plugin marketplace in the repo with `/larecipe:onboard`.
- *The pre-push check (L2).* `vendor/bin/larecipe-hook`, a dependency-free entry point that exits fast unless a Bash command pushes or opens a PR (`git push`, `gh pr create`, configurable). A Claude Code `PreToolUse` hook (and the Codex equivalent) runs `larecipe:hook pre-push`, which blocks once with the list of stale pages when mapped pages were not touched; loop guard (second attempt allowed, docs touched, `LARECIPE_HOOKS=off`); message follows the review mode. SessionStart injects the docs map. The same check as an optional git pre-push hook for people (warns only, `--strict` to block). No Stop hook. Installer merges hook settings, never overwrites.
- *Optional no-AI CI check (L3).* Scaffolded `larecipe-docs-check.yml` on `pull_request`: `larecipe:validate` plus `larecipe:affected --fail-on-stale`, in `fail` or `comment` mode, `skip-docs` label override. It never writes, never runs an AI agent, and needs no secrets for repository sets. No CI agent, no post-merge docs PRs.
- *Review modes.* `review: required | auto`: in `required`, the instructions and the pre-push message tell the agent to show the user the docs diff before committing; in `auto`, to update and push without asking.
- *MCP author tools.* `write_page`, `move_page`, `delete_page`, `update_navigation`, `update_manifest`, `affected_pages` on the local server for repository sets.
- *Onboarding.* The optional onboarding step in `larecipe:install` and the standalone `larecipe:onboard`: audience presets (product users, API consumers, package users, internal team), agent CLI detection, the exact command shown, consent, streamed progress, validation, uncommitted result; the no-agent fallback.
- *Live references (advanced).* `ReferenceResolver` contract and Laravel resolvers for `<Route>`, `<Artisan>`, `<Model>` (with `larecipe:references:snapshot`) and `<Event>`; validation of missing targets; skills that prefer references over pasted values. Never `<Config>` or `<Env>`.
- *Gate blocks.* `<Can>` and `<Cannot>` across HTML, `.md`, search records and index variants, `llms.txt`, `skill.md`, MCP and Ask AI, with audience-variant caching.
- *Server-side agent.* `larecipe:sync` (laravel/ai `DocsMaintainer` on the owner's key), on demand only (`--audit`, a developer without a local agent), producing commits for repository sets, with dry-run and budgets. Never a CI default.
- *Ask AI.* `DocsAssistant`, streaming endpoint with the Vercel UI message protocol, React panel (lazy), citations validated, gate, rate limit, budget, feedback capture.
- *Extensions, advanced.* Override built-ins by name, `useSlotDefault`, per-component eject (`larecipe:ui:eject Sidebar`) and full eject with `larecipe:ui:diff`.
- *More components.* `Mermaid`, `Math`, `AccordionGroup`, `Button` (v2 compatibility).
- *Custom search drivers.* The `SearchDriver` contract, `LaRecipe::extendSearch()`, and the published driver contract tests.
- *Migration.* `larecipe:upgrade` (dry-run default, report plus JSON) covering config, navigation, alerts, Vue components, anchors, placeholders and detection of manual items.
- *Agent endpoint.* Opt-in `GET /{prefix}/ask?q=` JSON search for agents without MCP.
- *Traffic events.* `PageViewed`, `MarkdownServed`, `LlmsTxtServed`, `SkillServed`, `McpToolCalled`, `SearchPerformed`, `AskAiAnswered`, `FeedbackSubmitted`, each with the request kind (human, crawler, agent), so hosts can forward server-side and agent traffic to their own analytics. No built-in store.

**Exit criteria**

- [ ] The real v2 docs (larecipe-docs `2.2` content) migrate with `larecipe:upgrade --apply` plus the `larecipe-upgrade-v2` skill, and `larecipe:validate` passes with zero errors.
- [ ] A scripted Claude Code session changes documented code in a fixture app and tries to push: the pre-push hook stops it once with the list of stale pages, the agent updates those pages with `larecipe-update-docs`, and pushes them in the same commit set as the code. No CI agent is involved. The same flow works with Codex hooks.
- [ ] A second push attempt for the same commit, a push whose docs were already updated, and a push with `LARECIPE_HOOKS=off` all pass without a block; ordinary Bash commands never boot the framework.
- [ ] The generated `AGENTS.md` block and the pre-push message follow the review mode (`required`: show the user the docs diff first; `auto`: update and push); on a sample GitHub repo, the optional CI check reports stale pages in `fail` and `comment` modes and never pushes.
- [ ] `larecipe:install` with Claude Code available runs onboarding for the API consumers preset on the fixture app and produces starter pages with `sources:` set that pass `larecipe:validate`; without an agent, it prints the `larecipe:onboard` command instead.
- [ ] Ask AI answers fixture questions with valid citations (faked provider in CI, one real-provider smoke run before release).
- [ ] An example extension overrides a built-in slot with `useSlotDefault` and renders with a single React instance.
- [ ] Skills pass the agentskills.io validator and contain no Claude-only frontmatter keys.
- [ ] Renaming a route or artisan command in the fixture app makes `larecipe:validate` fail on the page that references it, and the `larecipe-update-docs` skill fixes it.
- [ ] A `<Can>` block is absent for a guest from HTML, `.md`, the search index, `llms.txt`, MCP and Ask AI (automated matrix).

---

## Phase 3 · Database source and languages → `3.0.0` stable

The database source is a headline item of the v3 vision, so 3.0 does not ship without it.

**Deliverables**

- *Database source.* Migrations, configurable models, revisions (pages, navigation, manifest), drafts, publish, reject and revert, asset storage on any disk, always behind `CachedSource` (fingerprint in the cache, warm on publish, `larecipe:cache:warm`).
- *Docs migrations.* The `LaRecipe\Migrations\DocsMigration` base class with declarative operations (create, update, move and delete page; update navigation; update manifest), the registered `database/migrations/docs/` path, built-in `down()` from recorded revisions, `basedOn` conflict safety that turns conflicts into drafts without failing the deploy, generation by the local MCP author tools (one file per branch, applied locally for preview), `larecipe:make:docs-migration`, `larecipe:pull`, skills and `AGENTS.md` instructions that know the flow, and the pre-push hook checking the branch's docs migration.
- *Admin UI.* `/{prefix}/_admin` behind `manageLarecipe`: page tree and navigation editor, CodeMirror editor with server-rendered live preview, frontmatter, manifest and navigation forms from the schemas, review queue (migration conflicts first, then agent drafts), revision history with diff and revert, assets; a read-only overview for repository sets.
- *Remote author tools and review modes for database sets.* Authenticated web MCP author tools (Sanctum or OAuth) and `larecipe:sync --remote` for changes made outside a PR: drafts in `review: required`, published revisions in `review: auto`.
- *Switching.* `larecipe:source:move` (dry-run, verify by content hash) in both directions, `larecipe:source:export` and `import` with the bundle format.
- *Static export (advanced).* `larecipe:export` from either driver, with the client-side search index, so the exported site works offline; it reports what it left out (Ask AI, MCP, private sets).
- *Agent readiness.* `php artisan larecipe:doctor` grades each set against the AFDocs checks and LaRecipe's own (cache store, search budget, open migration conflicts) and prints fixes; the admin UI shows the same report.
- *Access control complete.* Version and page visibility, signed share links (`larecipe:share`), MCP web authentication for private sets, the Same-Rules test matrix across every surface.
- *Hardening.* Security review (sanitization, MCP, docs migrations, custom components, prompt injection), performance pass against budgets, API freeze for the 3.x line, complete docs and upgrade guide.
- *2.x.* Deprecation notice in the 2.x README and docs; `binarytorch/larecipe` marked abandoned on Packagist with `larecipe/larecipe` as its replacement.
- *Multi-language docs.* Locales declared in the manifest, `docs/{version}/{locale}/...` folders (database: a locale column), `/{prefix}/{locale}/{version}/{path}` URLs for non-default locales, language switcher that keeps the reader on the same page, fallback to the default locale with a notice, `hreflang` alternates, per-locale navigation, search index, `llms.txt`, `skill.md` and MCP scope, RTL from the locale's `dir` (Arabic with Thmanyah Sans), a translation workflow in `larecipe-update-docs`, and `larecipe:affected --locale` for translations that fell behind their source page.

**Exit criteria**

- [ ] Both drivers pass the source contract suite.
- [ ] Moving the larecipe-docs content repository → database → repository is lossless (identical content hashes).
- [ ] A docs migration generated in an agent session (by the local MCP tools, pushed with the code) applies on deploy through `php artisan migrate`; `php artisan migrate:rollback` restores the previous revisions; a migration whose page changed in production since becomes a conflict draft, prints a warning and does not fail the deploy.
- [ ] The admin UI resolves a migration conflict, and publishes a draft written by an agent through the remote author tools under `review: required`.
- [ ] A warm cached page on a database set runs zero database queries.
- [ ] `larecipe:export` produces a site that works offline, search included.
- [ ] A private page is absent from HTML, `.md`, `llms.txt`, sitemap, search, MCP and Ask AI for guests (automated matrix).
- [ ] A two-locale set (English and Arabic) serves both languages with the switcher keeping the page, falls back with a notice for a missing translation, emits `hreflang`, renders Arabic RTL, and scopes search, `llms.txt` and MCP per locale; `larecipe:affected --locale=ar` lists translations older than their English source.
- [ ] larecipe-docs runs in production on `3.0.0`.

---

## Phase 4 · Reach → `3.1+`

Candidates, ordered by expected value; final order set after 3.0 feedback.

- **Standalone CLI**: the portable domains, the exporter and a dev server packaged as a PHAR or static binary, so non-Laravel repos can use LaRecipe.
- **OpenAPI pages** (replaces `larecipe-swagger`): an `openapi` page type reading Scramble, Scribe or a spec URL, rendered with an embedded Scalar island, agent Markdown generated from the spec. Integration, not a new renderer.
- **Docs migrations, later**: a squash command and assets in migrations, if teams ask (`ARCHITECTURE.md` §24).

---

## Parallel track · larecipe-docs on v3

A fresh Laravel 13 app, not a migration of the current site, built on each phase's release. It is the main proof of the value proposition, so it has to show the agent story, not describe it. There is no separate marketing homepage: the docs set is mounted at the domain root, so larecipe.saleem.dev opens on the docs Introduction page.

| Milestone | With | Scope |
| --- | --- | --- |
| D1 · Skeleton | `3.0.0-alpha.1` | Fresh app consuming the package through a Composer path repository during development; the `docs` set at the domain root; core v3 pages (introduction, installation, writing docs, components, custom components, configuration, agent surfaces). The Introduction page reserves a space near the top for the maintainer's demo video, shown as a clearly labelled `<Video placeholder>` until the recording exists. The `Subscribe` newsletter component (React, `@larecipe/ui`, posting to `POST /newsletter/subscribe`, which calls `Nashra::subscribers()->create()` from `nashra/sdk`), inline on the Introduction page and at the `article.after` insert point site-wide. |
| D2 · Agent-maintained | `3.0.0-beta.1` | Full v3 content written and maintained through the shipped skills, the `AGENTS.md` instructions and the pre-push hook, said openly on the site; onboarding shown on a real app; Ask AI live; v2 docs kept as an archived version; migration guide. |
| D3 · Launch | `3.0.0` | Production deploy; the demo video in place; live demos of the value props (agent Markdown, MCP install, Ask AI, provenance on pages, a docs migration in a real PR); extension and showcase gallery (replaces the v2 packages directory; open question). |

---

## Maintenance track · 2.x

- Security and Laravel-compatibility fixes only, under `binarytorch/larecipe`, until `3.0.0` ships.
- After 3.0: README and docs banner pointing to v3 and the upgrade guide. `binarytorch/larecipe` is marked abandoned on Packagist with `larecipe/larecipe` as its replacement, so `composer` itself tells users where to go. If the GitHub repo moves to the `larecipe` organization, GitHub redirects keep links and stars.

---

## What this session builds

Decided by the maintainer on 2026-10-17: build all of 3.0 (Phases 1, 2 and 3) plus multi-language docs now, and deliver a fully working v3 at the end. Phase 4 (standalone CLI, OpenAPI pages, docs-migration squash) stays after 3.0.

The order inside the session follows the dependencies, not separate releases:

1. **Foundation (one author).** The `v3` branch restructured, `composer.json` for `larecipe/larecipe`, the `src/Domains/` layout with per-domain service providers and arch tests, the content model, `larecipe.json` and `navigation.json` loaders with schemas, the repository source, the Markdown pipeline skeleton, HTTP with content negotiation and the Blade prerender shell, the React app skeleton with the Vite build into `dist/`, Pest with Testbench, and a workbench app with fixture docs. Every later track copies its patterns.
2. **Parallel tracks**, each in its own git worktree, each merged only when its tests pass:
   - Markdown components, Phiki highlighting, agent Markdown, search records and the server ranker.
   - The React reading experience in "The Pass" design: shell, article adoption, client navigation, MiniSearch palette, page actions, Ask panel UI, `@larecipe/ui`, `@larecipe/vite` and custom components with insert points.
   - Agent surfaces: `.md`, `llms.txt`, `skill.md`, the agent-skills index, the MCP server and tools, `larecipe:validate`.
   - Agent workflows: `sources:`, `larecipe:affected`, the `AGENTS.md` block, the pre-push hook, the optional CI check, the four skills, Boost guidelines, the plugin marketplace, `larecipe:install` and onboarding.
   - AI: Ask AI and `larecipe:sync` on `laravel/ai`.
   - The database source, docs migrations, the admin UI and source moves.
   - Access control (gates, `<Can>` blocks, share links), live references, multi-language docs, static export, `larecipe:doctor` and the v2 upgrade.
3. **larecipe-docs, rebuilt** as a fresh Laravel 13 app on v3: the docs at the domain root, the Introduction page with the demo video slot, the `Subscribe` component, and the full v3 documentation written in v3.
4. **Verification.** The whole test suite, the JavaScript-disabled pass, the AFDocs check, and a browser walkthrough of larecipe-docs in light, dark, LTR and RTL.

Outside this repo's control: recording the demo video, publishing to Packagist and npm, deploying larecipe.saleem.dev, running the optional CI check on a real GitHub pull request, and real AI provider calls (fakes in tests unless a key is provided).

---

## Risks

| Risk | Mitigation |
| --- | --- |
| laravel/ai and laravel/mcp are young (1.x) and may change APIs | Keep both behind thin adapters (the `Mcp/` and `Laravel/` folders of each domain); pin minor ranges; contract tests on our tool classes. |
| Article adoption (React taking over server HTML) is a custom technique | Prototype first in Phase 1 with Playwright checks for CLS, scroll and media state before building the rest of the UI. |
| Scope pressure from long feature wish lists | Exit criteria per phase; Nice-to-haves go to Phase 4 or the backlog, not into 3.0. |
| Single-maintainer bandwidth | Phases are shippable slices; agents do most implementation with the shipped skills; contract, arch and snapshot tests keep agent-written code honest. |
| Package rename confuses existing users | Packagist abandonment notice with replacement, upgrade command that prints the swap, clear docs banner. |
| Live references expose app internals | No `<Config>` or `<Env>` by rule; model introspection limited to allowlisted namespaces; resolvers expose only declared route and command shapes; arch test forbids config and environment reads in resolvers. |
| Docs migrations clash with production edits | `basedOn` hashes turn clashes into conflict drafts instead of overwrites; `larecipe:pull` starts work from what is live; the admin UI puts conflicts first; deploys never fail on a docs conflict. |
| Agents ignore the instructions, or the pre-push hook annoys developers | Instructions in `AGENTS.md` plus a hook at the exact moment of the push; the hook blocks at most once per commit, explains itself in two lines, and is off with `LARECIPE_HOOKS=off`; the git hook and the no-AI CI check catch what agents without hooks miss. |
| Client search indexes grow too large | Indexes split per version, locale and audience; a size budget checked by `larecipe:validate`; the `server` driver and custom drivers for large sets. |
| Custom components break the single-React guarantee or the no-JS promise | Import-map React sharing tested in CI; PHP definitions require a fallback and a Markdown form; the Phase 1 exit criterion covers both on larecipe-docs. |
| Agent tooling standards shift (skills fields, hooks, llms.txt v2) | Portable spec-only skill frontmatter, one `larecipe:hook` entry point, generated (not hand-written) agent files that `larecipe:agents` can regenerate. |

---

## Dropped by decision

Items considered for v3 and not shipped, with the reason.

| Item | Why |
| --- | --- |
| Remote git source (separate docs repository, provider-API import, git webhooks, atomic swaps) | Docs belong in the app's own repo so one PR changes code and docs together. Teams that want docs outside the repo use the database source with an admin UI. |
| Algolia DocSearch driver | Search is internal and client-side by default; teams with an external engine write a custom `SearchDriver`. |
| Laravel Scout search driver | Same reason; the `SearchDriver` contract covers custom engines without a Scout dependency. |
| `<Config>` and `<Env>` live references | Configuration and environment values are where secrets live; no allowlist is safe enough for docs. |
| Post-merge "open a docs PR" automation as a default | Docs changes land in the same PR as the code, written by the developer's own agent before it pushes. |
| An AI agent in CI that commits docs to the PR branch | The agent that changed the code has the context; instructions in `AGENTS.md` and a pre-push hook make it update the docs before it pushes. CI may check, never write. |
| Built-in traffic insight (metrics store, rollups, dashboards, agent-traffic charts, stored feedback) | Teams already have analytics; LaRecipe feeds them through integrations and events with a request kind instead of building a second dashboard. |
| Self-healing docs | Depended on stored feedback and search data, which LaRecipe no longer keeps. |
| Importers from other docs tools (laravel/docs and others) | Focus on new docs and on v2 users; `larecipe:install` onboarding writes real starter docs from the app instead. |
| In-app contextual help widget (`<x-larecipe::help>`, React widget) | Out of scope for a docs engine in 3.x; custom components and insert points cover site-level needs. |
| Drafts tagged with a PR ref that publish on merge | Replaced by docs migrations: database changes ride in the PR as standard Laravel migrations and apply on deploy. |
| A separate marketing homepage for LaRecipe | The docs are the landing page; the Introduction page carries the demo video. |

---

## Decision log

| Date | Decision | By |
| --- | --- | --- |
| 2026-10-17 | Laravel-native package now, designed so a standalone CLI can be added later. | Maintainer |
| 2026-10-17 | Strategy and design docs first, review, then build. | Maintainer |
| 2026-10-17 | Show two or three brand directions before committing to one. | Maintainer |
| 2026-10-17 | No backward compatibility; provide an AI-assisted migration path from v2. | Maintainer |
| 2026-10-17 | Package name `larecipe/larecipe`, namespace `LaRecipe\`; `binarytorch/larecipe` stays on 2.x and is marked abandoned once 3.0 ships. | Maintainer |
| 2026-10-17 | Brand direction A, "The Pass": paper and ink, one violet for state, the ticket as the signature shape. | Maintainer |
| 2026-10-17 | Type follows nashra.ai: Inter (`ss01`, `cv11`) for English, Thmanyah Sans for Arabic; JetBrains Mono for labels, tickets and code. | Maintainer |
| 2026-10-17 | Every LaRecipe site gets the LaRecipe design system by default; teams retheme it through tokens (accent, fonts, radius, logo). | Maintainer |
| 2026-10-17 | Docs live in the app's repo (recommended) or the database (with an admin UI, always behind a cache). No separate docs repository. | Maintainer |
| 2026-10-17 | Database content changes made in a PR ship as docs migrations: standard Laravel migrations in `database/migrations/docs/`, reviewed in the PR, applied by `php artisan migrate` on deploy, reversible and conflict-safe. Admin UI edits are direct database changes with revisions. | Maintainer |
| 2026-10-17 | The owner's own coding-agent session is the primary author; laravel/ai with the owner's key only when extra power is needed. | Maintainer |
| 2026-10-17 | Docs changes land in the same PR as the code; no post-merge docs PRs by default. | Maintainer |
| 2026-10-17 | Review is required by default for changes made outside a PR; owners may opt into auto mode (no review). Changes in a PR are reviewed in the PR. Always revisioned and attributed. | Maintainer |
| 2026-10-17 | Keep `larecipe.json` and per-version `navigation.json`; YAML frontmatter is for page-level config only. | Maintainer |
| 2026-10-17 | Search is internal and client-side fuzzy with MiniSearch (confirmed by the maintainer); no Algolia; custom search drivers for advanced cases. | Maintainer |
| 2026-10-17 | Static export is an advanced, opt-in feature (Phase 3). | Maintainer |
| 2026-10-17 | Live references never include `<Config>` or `<Env>`. | Maintainer |
| 2026-10-17 | Optional AI onboarding during install, run by the owner's local coding agent with the `larecipe-write-docs` skill, for chosen audiences. | Maintainer |
| 2026-10-17 | Keep four skills (`larecipe-write-docs`, `larecipe-update-docs`, `larecipe-audit-docs`, `larecipe-upgrade-v2`). | Maintainer |
| 2026-10-17 | LaRecipe's own landing page is the docs (Introduction at the domain root), with a slot for the maintainer's demo video. | Maintainer |
| 2026-10-17 | Backend organized into domains, Nashra style, with readable Actions and DTOs. | Maintainer |
| 2026-10-17 | Developers can add their own React components in Phase 1, inline in Markdown or site-wide at insert points, with runtime helpers to talk to the host app; larecipe.saleem.dev ships a `Subscribe` newsletter component backed by `nashra/sdk`. | Maintainer |
| 2026-10-17 | Docs updates come from agent instructions in the repo: `larecipe:install` writes an `AGENTS.md` block (or the Boost guideline) telling every AI session to update affected docs before it pushes, reinforced by a pre-push agent hook that blocks once. No AI agent runs in CI; at most an optional no-AI check that never writes. `larecipe:sync` is on demand only. | Maintainer |
| 2026-10-17 | No built-in traffic insight: no metrics store, rollups or dashboards. Analytics integrations in `larecipe.json` plus Laravel events (with a request kind) that hosts forward to their own tools; feedback is an event, not a table. | Maintainer |
| 2026-10-17 | No importers from other docs tools and no in-app help widget. The v2 upgrade stays. | Maintainer |
| 2026-10-17 | Cut OG image generation, semantic retrieval for Ask AI, discovery extras (context7.json, MCP server card, WebMCP), the standalone `larecipe/agent-surface` package and MCP Apps. | Maintainer |
| 2026-10-17 | Multi-language docs move into 3.0. Build all of 3.0 in this session and deliver a fully working v3. | Maintainer |
