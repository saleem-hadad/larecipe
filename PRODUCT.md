# LaRecipe: Brand, Product, and Design Context

> Read this before shaping product, designing interfaces, writing copy, or shipping UI. This is the single source of truth for what LaRecipe v3 is, who it serves, and how it should sound. How it is built lives in `ARCHITECTURE.md`. How it looks lives in `DESIGN.md` and `ART_DIRECTION.md`. Sequencing lives in `ROADMAP.md`. Market evidence lives in `COMPETITIVE_RESEARCH.md`.

Status: draft for maintainer review, 2026-10-17.

---

## Brand Strategy

### October 2026 positioning

**Who it is for, in priority order:**

1. **Laravel product teams.** SaaS companies and product teams whose customers need docs: product guides, API references, integration walkthroughs. They want Mintlify-class docs without a $450 monthly bill, a second stack, or a separate login. Their docs should live in the app they already deploy.
2. **Laravel package authors.** Open-source maintainers who need versioned docs for their package. In 2026 a package that agents cannot read is a package agents use wrong. Agent-readable docs are now an adoption feature.
3. **Internal engineering teams.** Teams documenting their own app: architecture notes, runbooks, onboarding, domain glossaries, behind the app's own auth. These docs double as their coding agents' context through the local MCP server.

**The problem.** Docs rot. Code changes every day, and with coding agents it changes faster than ever, so docs fall behind the moment they ship. Nobody writes docs by hand any more, yet docs tools were built for people typing Markdown into a separate project. Most docs readers are now agents (66% of requests to Mintlify-hosted docs in July 2026), yet most docs tools still optimize for a browser. The tools that do take agents seriously are hosted, metered and outside your app.

**The pitch.** LaRecipe is the docs engine that lives inside your Laravel app. Your coding agents write the docs and keep them current with every change. Every reader, human or agent, gets the current answer. You review; LaRecipe does the rest.

**The category line:** Living documentation for Laravel apps.

**The hero:**

- H1: **Docs that keep up with your code.**
- Support: LaRecipe lives inside your Laravel app. Your coding agents write the docs and update them with every change. People get a fast, beautiful site; agents get Markdown, `llms.txt` and MCP.
- CTA: `composer require larecipe/larecipe` as a copyable command, with **Read the docs** as the secondary action.

**The mechanism stays, as the support.** "Push code. The docs follow." explains how. It sits under the pitch on every surface, never above it.

**The heritage stays.** LaRecipe has been the Laravel docs package since 2018: 3 million installs, 2.5k stars, and a first line that said "Writing documentation is boring? Hah, let's see." v3 keeps that confidence and that wink. It drops Vue, the theme sprawl and the idea that a human sits down to write every page.

### The Living Docs Strategy

LaRecipe is the documentation layer of a Laravel app.

The code is the source of truth.

The docs are a reviewed projection of it.

Agents keep the projection current.

Every reader gets the same truth, in the form that suits them.

AI works across that loop. It writes and updates pages from real code, flags pages a change made stale, answers readers with citations, and reads the docs back as context for the next change. It is not a chatbot bolted onto a docs site, and it never publishes without a human in the loop by default.

### Agents as a System Attribute

Nashra's rule is that AI never leads. LaRecipe's is narrower: **agents lead the mechanism, outcomes lead the message.** The reason LaRecipe exists is that agents now write the docs, so hiding them would be dishonest. But nobody buys "AI-powered docs". They buy docs that are current, trustworthy and readable by everything that reads them.

- **Write.** Skills, MCP tools and validation make any coding agent (Claude Code, Codex, Cursor, Copilot) good at writing LaRecipe docs from the code it just changed.
- **Keep current, in the same PR.** Pages declare the code they describe, and a change maps to the pages it made stale. LaRecipe writes instructions into `AGENTS.md` at the repo root, so every AI coding session knows to update the affected docs just before it pushes or opens a pull request. A pre-push hook in the agent catches the moment it forgets. The docs travel in the same push as the code. Never a separate docs PR, never an agent running in CI.
- **Owner's session first.** The default writer is the agent the developer already runs, on their own subscription. When extra power is needed (a whole-site audit, or a developer without a local agent), `php artisan larecipe:sync` runs an agent built on `laravel/ai` on the owner's provider key, on demand.
- **Ground.** Live reference components (`<Route>`, `<Artisan>`, `<Model>`, `<Event>`) read facts from the running app, so the most common kind of drift cannot happen. Config and environment values are never exposed by any component.
- **Serve.** Every page has a Markdown twin, `llms.txt` indexes, a `skill.md`, and an MCP server. Ask AI answers readers from the docs with citations.
- **Review, or auto.** By default everything an agent writes is reviewable: commits in the pull request (Markdown for the repository source, docs migrations for the database source), or drafts when an agent works outside a pull request. An owner who trusts the loop can switch to `auto` and let docs update with no review step, still revisioned, attributed and one click to revert.

The developer remains the editor. AI output is specific, inspectable, editable and attributed. LaRecipe does not publish, delete or rewrite docs without an explicit action, or the owner's explicit opt-in to `auto`.

### The Problem

Docs are expected to be four things at once: correct, complete, findable and readable by agents. Today they are rarely any of them for long.

- **Correct on launch day, wrong by Friday.** The route got renamed, the config key moved, the command gained a flag. Nobody remembered the docs page.
- **A separate project.** Another repo or folder, another framework, another build, another deploy, another login for private docs.
- **Handwritten busywork.** People retyping what the code already says, for readers who mostly arrive through an agent.
- **Invisible to agents.** JavaScript-rendered pages, no Markdown, no MCP. The agent guesses, and the reader gets a wrong answer they blame on your product.
- **Rented.** The tools with good agent features charge per seat, per site and per answer, and keep your docs in their database.

The real work is no longer writing docs. It is keeping them true.

That is why v3 exists.

### Our Belief

Docs are part of the product, so they belong with the code.

The agent that just changed the code is the best author of the docs for that change. It has the full context right now, and in an hour it will not.

Readers deserve to know whether a page is current. Agents deserve the same content as people, in a form they can use.

### Our Insight

Keeping docs current is a detection problem first and a writing problem second.

If a change can be mapped to the pages it affects (deterministically, for free), then any agent can do the writing and any human can do the review. Most tools start with the writing. LaRecipe starts with the map, then equips the agent, then shows the reader what changed.

### Our Solution

LaRecipe gives a Laravel app one docs engine:

- Docs live in your repo (recommended) or in your database with an admin UI, in one portable format (Markdown with YAML frontmatter for pages, plus `larecipe.json` and `navigation.json` for the doc set), and can move between the two at any time. In the database, content changes made in a pull request ship as migration files, reviewed with the code and applied on deploy.
- Your coding agent writes and updates them, guided by four LaRecipe skills (write, update, audit, upgrade), MCP tools, hooks and a deterministic validator. On install, it can write your first real docs from the app itself.
- Each change maps to the pages it affects, so drift is caught in the agent session just before the push, and the docs ship in the same pull request.
- One renderer serves people a fast React site with instant search and serves agents Markdown, `llms.txt`, `skill.md` and MCP, under one access policy.

The result is a loop:

```text
Change the code
   ↓
Your agent updates the affected pages in the same pull request
   ↓
You review them with the code (or let them through, in auto mode)
   ↓
Every reader, human or agent, gets the current answer
   ↓
Your agent reads the docs back while making the next change
```

### The Core Promise

**Push code. The docs follow.**

This does not mean LaRecipe rewrites docs behind your back. It means every pull request carries its own docs update without anyone remembering to write one, reviewed with the code unless the owner chose `auto`, and every reader can see that it happened.

### Product Architecture

```text
Code changes in an agent session, before the push
                      ↓
   Write: skills · MCP tools · hooks · larecipe:sync
                      ↓
   One format: Markdown + frontmatter + JSON
   repository (recommended) · database + admin UI
                      ↓
 One renderer · one access policy · one search index
          ↙                               ↘
 People: React site, search,       Agents: .md, llms.txt,
 Ask AI, page actions              skill.md, MCP
```

### Internal Vision

Internally, LaRecipe is building **the docs loop**: code, docs and agents feeding each other continuously.

Every feature should help one of these:

- Write docs from the code
- Keep docs true to the code
- Serve docs to every reader, human or agent
- Keep the docs owned by the team that ships the code

If a feature does none of these, it likely does not belong.

### External Positioning

Customers hear outcomes, not infrastructure.

The external category is **living documentation for Laravel apps.**

The primary promise is **Push code. The docs follow.**

Supporting messages:

- Docs that keep up with your code.
- Your agents write the docs, in the same pull request as the code.
- Every page says when it changed, and why.
- Agent-ready from the first page: Markdown, `llms.txt`, `skill.md` and MCP, built in.
- Lives inside your Laravel app: your auth, your gates, your deploy.
- Docs in your repo, or in your database with an admin UI. Switch whenever you like.
- Open source. Your agent session or your keys, your data, no meters.

"Docs loop" may appear in founder narratives and architecture talks. It should not lead customer copy.

**The stress of all messaging is freshness.** LaRecipe docs stay current, and they can prove it. Push this one idea harder than any feature list. Claims must track shipped reality: say "updates with every change" only about a workflow that has actually shipped and been tested.

### The landing page is the docs

LaRecipe has no separate marketing homepage. larecipe.saleem.dev opens on the Introduction page of the docs, served at the domain root and built with LaRecipe v3. The product is the pitch: the first thing a visitor sees is a LaRecipe docs page doing exactly what the copy promises (provenance, page actions for agents, instant search, Ask the docs).

The Introduction page, in this order:

1. **The promise.** H1: Docs that keep up with your code. Lead: the support line.
2. **The demo video.** A two to three minute video of the maintainer explaining LaRecipe, directly under the lead. Until it is recorded, the slot is a clearly labelled placeholder (`<Video>` with no source renders a "Demo video coming soon" frame), never a fake.
3. **Install in two commands.** `composer require larecipe/larecipe` and `php artisan larecipe:install`, with a line about the optional onboarding that writes your first docs from your app.
4. **The loop.** Write, keep current, serve, own: four linked cards, each to the page that proves it.
5. **Every reader.** A short note that this very page is also available as Markdown, in `llms.txt` and through MCP, with the links.
6. **Upgrading from v2.** One command and the `larecipe-upgrade-v2` skill.
7. **Stay in the loop.** A newsletter subscribe form, which is itself a custom React component added by the site (it also sits after every article). It doubles as the live demo of custom components.

Every claim on the page links to the page that proves it.

### Strategic Difference

Static generators render what you write.

Hosted platforms rent you agents, by the seat and by the answer.

Laravel docs packages serve files.

LaRecipe runs the whole loop inside your app:

```text
Write from the code (your agent)
        +
Keep it true to the code (drift detection, live references)
        +
Serve every reader (people and agents, same rules)
        +
Own all of it (open source, your repo or your database, your agent or your keys)
```

| | Static generators (Docusaurus, Starlight, Fumadocs) | Hosted platforms (Mintlify, GitBook, documentation.ai) | Laravel packages (LaraDocs, Vellum) | LaRecipe v3 |
| --- | --- | --- | --- | --- |
| Lives in your Laravel app | No, separate Node project | No, separate hosted site | Yes | Yes |
| Agents write and update docs | No | Yes, metered | No | Yes, in your own agent session, before every push |
| Drift detection from code changes | No | Repo-text only | No | Yes, plus live references from the running app |
| Agent read surfaces | Plugins | Yes | Partial | Yes, built in |
| Content sources | Files | Their database | Files | In-repo files or your database with an admin UI, switchable |
| Search | Client index | Hosted, often metered | Scout | Instant client-side fuzzy search, custom drivers for advanced cases |
| Private docs | DIY | Enterprise tiers | Yes | Your gates, on every surface |
| Price | Free | $150 to $500 a month plus credits | Free | Free |

**On LaraDocs.** It launched in June 2026 and reached 37% of LaRecipe's monthly installs in four months, with `llms.txt`, an MCP server and AI chat. It proves the demand and sets the floor. It is Blade-only, file-only, has no authoring loop and no per-page Markdown. We never name it in public copy; we out-ship it. The internal lesson is speed: publish alphas early and often.

### Product Principles

1. **Agents write, humans approve, unless the owner says otherwise.** By default everything an agent writes is reviewable: commits in the pull request (docs migrations, for the database source), or drafts when an agent works outside a pull request. `auto` mode, opted into by the owner, removes the review step; every change stays revisioned, attributed and reversible.
2. **Docs live with the code.** In the app's own repo (recommended) or the app's own database. Never a second repo, never a second project, same deploy, same auth.
3. **Every reader is first-class.** People and agents get the same content under the same access rules, in the form that suits them. No cloaking.
4. **Deterministic first.** Validation, links, navigation and drift detection are free and reproducible. AI is layered on top, never underneath.
5. **Freshness is visible.** Every page can say when it changed, what changed it, and who approved it.
6. **Own everything.** Open source, self-hosted, portable format, your AI keys, no telemetry, no meters, switch sources at any time.
7. **Two minutes to a working site.** `composer require`, one install command, no Node, no build step. Opinionated defaults; complexity is earned.
8. **Gorgeous by default.** LaRecipe's first promise was beautiful docs. A default LaRecipe site should look better than most teams would design themselves.
9. **Laravel-native, not Laravel-flavored.** Gates, Eloquent, Artisan, queues, cache, Boost, `laravel/ai`, `laravel/mcp`. Use the framework; never wrap it in a second one.
10. **Simple by default, powerful when asked.** Defaults cover most teams: docs in the repo, client-side search, the owner's agent session. Custom search drivers, static export, the database source and live references are there for teams who need them, and never in the way of those who don't.

### The North Star

Developers should stop thinking:

> "I need to update the docs."

They should think:

> "The docs changed in the same PR."

Readers should stop wondering:

> "Is this page still true?"

The page should answer:

> "Updated two hours ago, with the change that made it necessary."

Agents should stop guessing. They should search the docs, read the page and get it right the first time.

### Feature Decision Test

Before building a feature, ask:

1. Does it keep docs closer to the truth of the code?
2. Does it make docs better for people who read them?
3. Does it make docs better for agents that read them?
4. Does it reduce the work of keeping docs current?
5. Does it preserve ownership (self-hosted, portable, open, no meters)?
6. Does it work with zero configuration and sensible defaults?

If the answer to all six is no, the feature likely does not belong. If it fails 5, it does not belong at all.

### Success Test

After merging a change, the developer should never wonder:

> "Which docs page did this break?"

The answer should be:

**None. The agent already updated it in the same pull request.**

After landing on a page, a reader should never wonder:

> "Is this still accurate?"

The answer should be:

**The page tells you what changed, and when.**

### Licensing and sustainability

LaRecipe v3 is open source (MIT) and free to self-host. No telemetry, no usage meters, no required cloud service. AI features run on the owner's `laravel/ai` provider keys.

The sustainability model (sponsorship, paid add-ons, a hosted sync runner) is undecided and is the maintainer's call. Whatever it becomes, it must pass the Feature Decision Test, question 5.

### Long-Term Vision

LaRecipe will own the idea of **living documentation** in the Laravel ecosystem.

Docs will stop being a project that a team maintains. They will be a property of the codebase: written by the agents that change it, verified against it, and served to everyone and everything that needs it.

Every time a LaRecipe team ships, their docs become more accurate, their agents become more effective, and their readers trust the docs a little more.

LaRecipe exists to make that loop the default for every Laravel app, free, from the first `composer require`.

---

## Design Context

### Users

**Primary ICPs (priority order, October 2026):**

1. **Laravel product teams.** Two to fifty engineers, a customer-facing product, often an API. They judge docs tools on how the result looks to their customers, how much it costs, and whether it fits their deploy. They already use Claude Code, Codex or Cursor daily.
2. **Laravel package authors.** One to three maintainers, versioned releases, a community that asks the same questions repeatedly. They care about versioning, agent-readability and zero maintenance.
3. **Internal engineering teams.** Private docs behind the app's auth: architecture, runbooks, onboarding. They care about access control, search, and their agents having the context.

**Job to be done:** Ship code and have the docs follow, reviewed and correct, for every reader, without a second project or a monthly bill.

**Emotional goals:** Relief and pride. Relief that docs are no longer a chore that rots; pride when a customer, a contributor or an agent reads them.

### Brand Personality

**Warm. Precise. Generous.**

Three voice beats:

- **Warm and direct.** Short sentences, plain words, a human on the other side. A little wit is welcome, once per page at most: LaRecipe has always had a wink. No exclamation marks, no emoji in product UI.
- **Precise.** Technical claims are exact. Commands are copyable and correct. Examples come from real code. Numbers are real or absent.
- **Generous.** Give the answer first, then the reasoning. Defaults that work. Errors that say what to do next. Docs about docs that are themselves the best example.

**Voice-check rubric (run before shipping any copy):**

1. Would a senior Laravel developer read this without rolling their eyes?
2. Is every technical claim true today, in shipped code?
3. Is there a shorter version? (Almost always.)
4. Does it sound like a person who built the thing, or like marketing?

### Aesthetic Direction

**Direction A, "The Pass"** (chosen 2026-10-17). In a kitchen, the pass is where every plate is checked before it leaves. Code changes fire tickets, agents prepare the docs, a human signs them off. Precise, monochrome, technical: paper and ink, one violet that only means state, mono labels for what machines produced, and the ticket (perforated header, bitten bottom edge) as the signature shape.

**Type follows nashra.ai:** Inter (with `ss01` and `cv11`) for English, Thmanyah Sans for Arabic, JetBrains Mono for labels, tickets and code.

**Default theme:** every LaRecipe site ships with this system. Teams retheme it through tokens (accent, fonts, radius, logo); the structure and the signature elements stay.

Tokens and components live in `DESIGN.md` and `DESIGN.json`. Feel, motion and the signature moves live in `ART_DIRECTION.md`. The comparison canvas (A, its Arabic RTL rendering, and the two directions not taken) is at https://claude.ai/artifact/F5BTSotLiooEu9QdsTw1qW.

**Anti-references (whatever the direction):**

- **The generic AI-docs look.** Purple-to-blue gradients, sparkle icons, glassmorphism, a floating chat bubble over the content.
- **The default docs template.** The Mintlify/Fumadocs layout everyone ships, recognizable at a glance and owned by nobody.
- **v2's dated chrome.** Font Awesome icons, heavy shadows, Bootstrap-era cards and progress bars.

### Design Principles

1. **HTML first, instant always.** Every page is complete server HTML. React enhances; it never gates content. Navigation feels instant.
2. **The content is the hero.** Chrome recedes. The article, its code and its components lead.
3. **Freshness is designed, not appended.** Provenance (updated when, by what, approved by whom) is a first-class element of the page, not a footnote.
4. **Agent surfaces are visible.** Page actions (copy as Markdown, open in an agent, connect MCP) sit where readers can find them, because readers now work with agents.
5. **AI is calm.** No sparkles, no magic. AI shows its sources, its inputs and its output, and the reader stays in control.
6. **The customer's brand fills customer docs.** Every site starts from the LaRecipe system; a team's accent, logo and fonts replace LaRecipe's through tokens. LaRecipe's mark appears only as an optional, small "Built with LaRecipe" credit.
7. **Accessible and RTL-native.** WCAG 2.2 AA, keyboard-first, reduced motion, logical properties. Arabic and other RTL docs are peer renderings, not a theme.
8. **Breathing room.** Docs are read for long stretches. Pick the more generous spacing.

---

## The Five Enemies (what LaRecipe explicitly votes against)

1. **Docs rot.** Pages that were right on launch day and quietly wrong ever after.
2. **The separate docs project.** Another repo, another framework, another deploy, another login.
3. **Docs agents can't read.** Beautiful pages that serve an empty div to the reader that matters most.
4. **AI slop, published unreviewed.** Agent text that goes live without a diff, a reviewer or a trace.
5. **Meters and lock-in.** Your docs in someone else's database, priced per seat, per site and per answer.

When listing fewer than five, lead with **#1 (docs rot)** and **#4 (unreviewed AI slop)**.

---

## Vocabulary

**Use:**

- *Docs* in product and marketing copy. *Documentation* only in formal contexts.
- *Doc set* for one docs site inside an app (`/docs`, `/api`, `/help`).
- *Page*, *version*, *locale*, *navigation*.
- *Source* for where docs live: *repository* (the app's own repo, recommended) or *database*.
- *Frontmatter* for the YAML at the top of a page: page-level config only. *Manifest* (`larecipe.json`) for the doc set, *navigation* (`navigation.json`) for each version.
- *Docs migration* for a content change to the database source, shipped as a migration file in the pull request.
- *Review mode*: *required* (default) or *auto*.
- *Coding agent* for Claude Code, Codex, Cursor, Copilot and peers. *Agent* when the context is clear.
- *Skill*, *MCP server*, *hook*, by their standard names.
- *Stale page* and *drift* for docs that fell behind the code. *Affected pages* for what a change touched.
- *Live reference* for components that read values from the app.
- *Provenance* in technical docs; "Updated by" and "Approved by" in UI.
- *Draft*, *review*, *publish*.
- *Agent-ready* for docs that agents can read well.
- Verbs: *write, update, review, ship, serve, read.*

**The kitchen metaphor is seasoning, not the meal.** Direction A borrows the kitchen pass: *ticket*, *fired*, *the pass*, *signed off*, *86'd*. These words belong to LaRecipe's own surfaces (its site, CLI output, the admin review queue). On a team's docs, the ticket is a shape and the copy is plain ("Updated", "Last change", "Page not found"). Kitchen words never rename technical nouns: a page is a page, a version is a version. `ART_DIRECTION.md` governs the details.

**Avoid:**

- "AI-powered" as a category or headline lead. Name the concrete outcome instead.
- "Magic", "effortless", "supercharge", "unlock", "leverage", "revolutionary", "next-generation".
- "Documentation platform" as our category. We are not a hosted platform.
- "Chatbot" for Ask AI.
- "Writes itself" without naming the agent and who stays in control.
- Competitor names in headlines. Comparison pages may name them factually.
- Exclamation marks, emoji in product UI, em dashes in any copy.

**Never say:**

- "Docs that write themselves" as a promise with no agent, no owner and no audit trail behind it. (`auto` mode is the owner's choice, and we describe it as exactly that.)
- Fabricated metrics, fake testimonials, invented install counts.
- Anything a shipped feature cannot back up.

---

## Feature Map (v3)

Grouped by the loop. Phases refer to `ROADMAP.md`.

| Pillar | Features | Phase |
| --- | --- | --- |
| **Write** | `larecipe:install` with agent setup and optional onboarding (your first docs written from the app, for the audience you pick); skills (`larecipe-write-docs` with the onboarding workflow, `larecipe-update-docs`, `larecipe-audit-docs`, `larecipe-upgrade-v2`); Boost guidelines; local MCP author tools; `larecipe:validate --json`; Claude Code plugin | 1 to 2 |
| **Keep current** | `sources:` frontmatter; `larecipe:affected`; agent instructions in `AGENTS.md` (update docs before every push); a pre-push agent hook that runs the affected check; optional no-AI CI check; review modes (`required`, `auto`); live references (`<Route>`, `<Artisan>`, `<Model>`, `<Event>`, advanced); `larecipe:sync` on the owner's `laravel/ai` key | 2 to 3 |
| **Serve people** | React reading experience; instant client-side fuzzy search with Ask AI in the same palette; built-in components plus your own React components (inline in Markdown or placed site-wide, with no-JS and agent fallbacks); versions; multi-language docs (Arabic and other RTL languages as peer renderings); dark mode; page feedback (sent to your analytics); provenance on every page; analytics integrations (GA4, Plausible, Fathom, PostHog, custom) | 1 to 2 |
| **Serve agents** | `.md` twins and `Accept: text/markdown`; `llms.txt`; `skill.md`; MCP server; page actions; AFDocs grade A | 1 |
| **Own** | Repository source (recommended) and database source with an admin UI and a cache in front; lossless moves between them; docs migrations for database changes; JSON manifest and navigation with YAML page frontmatter; doc sets; gates on every surface; `<Can>` blocks; self-hosted AI; custom search drivers and static export (advanced); standalone CLI | 1 to 4 |
| **Upgrade** | `larecipe:upgrade` plus the `larecipe-upgrade-v2` skill | 2 |

---

## Operating Rules for Future Sessions

When in doubt, defer to:

1. `PRODUCT.md` (this file) for positioning, voice, naming and feature decisions.
2. `ARCHITECTURE.md` for technical decisions and its named rules.
3. `DESIGN.md` and `DESIGN.json` for tokens and components, and `ART_DIRECTION.md` for feel (once written).
4. `ROADMAP.md` for sequencing and exit criteria.
5. `COMPETITIVE_RESEARCH.md` for market evidence.

If a request would break one of these, surface it and ask before proceeding.
