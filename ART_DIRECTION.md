# LaRecipe · Art Direction & Vision

> The creative direction for LaRecipe v3. What it is, what it feels like, what it refuses to be. Sits next to `PRODUCT.md` (strategy) and `DESIGN.md` (tokens and components); it does not repeat them.

## I · Why this exists

LaRecipe has a strategy (`PRODUCT.md`), a design system (`DESIGN.md`, `DESIGN.json`) and an architecture (`ARCHITECTURE.md`). This document names what kind of object LaRecipe is, where its personality lives, and what it refuses to be.

Every designer, contributor and agent working on LaRecipe eventually asks: what should this feel like? The answer should not change between sessions or depend on who is at the desk. This is the answer.

## II · The vision

**What LaRecipe is.** Living documentation for Laravel apps. The docs live inside the app, agents write and update them with every change, a human signs the changes off, and every reader, human or agent, gets the current answer.

**The rosy horizon.** Mid-2027.

At 11pm in Lisbon, a package maintainer opens a pull request that renames a config key. Her Claude Code session already updated the Webhooks page before she pushed; the PR carries the docs commit next to the code: `TICKET 0318 · Claude Code updated Webhooks for #842 (signing_secret renamed)`. She reviews both diffs together, merges, and goes to bed. The page now says "Updated 4 minutes ago". She never opened the docs folder.

At 2pm in Riyadh, a support engineer opens the Arabic version of a product's docs. It reads as if it were written in Arabic. The commands stay left to right, the numerals stay readable, and the page tells her it changed yesterday, with the release.

At 3am, nobody is reading at all. A coding agent in someone's editor calls `search_docs`, reads the page as Markdown, and writes the integration correctly the first time. Nobody sees this reader. It is now most of the readers.

None of them are thinking about LaRecipe. They are thinking about their work. That is the rosy horizon: docs that stay true because the system around them makes staying true the default.

**Where we are now.** v2 renders Markdown well and looks like 2019: Vue 2, Tailwind 0.7, Font Awesome, a theme ecosystem that is mostly archived. v3 is a rebuild. This document is the line between the two.

## III · Who we're designing for

Three working sketches. Not personas; people someone on the team has met.

**The SaaS tech lead.** Twelve engineers, a Laravel API, enterprise customers who want private docs. Their docs are a separate Next.js repo that nobody has touched since the last launch. A Mintlify quote came back at $450 a month plus credits, outside their auth and their deploy. They live in Claude Code all day. They want the docs inside the app, behind their own gates, maintained by the same agents that write the code, and good-looking enough that their head of design does not wince.

**The package maintainer.** Two maintainers, a package with real adoption, a `docs/` folder in the repo, and the same five questions in the issues every week. Lately a new kind of bug report: users whose AI agents call the package wrong because the agents never read the docs. They need versioned docs, zero hosting effort, and docs that agents can actually read. They will not maintain a second project.

**The platform team.** Runbooks in Notion, architecture notes in a wiki, onboarding in a Google Doc, all stale. They want everything inside the app they run, private, searchable, and available to their coding agents as context. They care more about access control and search than about how the landing page looks.

## IV · The art direction

### IV.1 · Aesthetic anchor: The Pass

LaRecipe is the pass, the counter between the kitchen and the dining room.

In a restaurant, tickets print and hang above the pass. Cooks prepare the plates. The head chef checks every one before it leaves: right order, right table, nothing missing. The guest never sees the kitchen; they see a plate they can trust.

That is LaRecipe's job for documentation. Code changes fire tickets. Agents prepare the docs. A human signs them off at the pass. The reader receives a page that is correct and says so.

The aesthetic neighborhood is everything where small, exact print carries authority: thermal tickets, railway timetables, lab notebooks, the precision of the best API references. Quiet, monochrome, technical, with one color that means something happened. The metaphor lives in the structure (tickets, perforations, the pass) and never in illustration. There are no chef hats, no cartoon cooks, no puns on the page a customer's reader sees.

We are not the generic AI-docs look (gradients, sparkles, a chat bubble floating over the content). We are not the default docs template that makes every site look like its vendor. We are not v2's chrome. We are the pass.

Why this anchor for docs that agents maintain: trust is the whole product. When agents write most of the words, the question every reader silently asks is "can I rely on this?" The pass is the image of exactly that check.

### IV.2 · The atmospheres

Three scenes drive theme, contrast and density decisions.

**The merge at 11pm.** A developer alone, reviewing a docs diff an agent produced. Dim room, focused, a little tired. Dark mode is the kitchen after service: near-black night, the screen the only light, violet softened so it does not glare. Nothing competes with the diff.

**The support desk at 2pm.** Bright office, a customer on the line, scanning for an answer. Light mode is thermal paper under a pass lamp: warm white, firm headings, instant search, the answer above the fold. Speed is the courtesy.

**The agent at 3am.** No pixels at all. Its interface is the Markdown projection, the `llms.txt` index, the MCP tool descriptions, the error messages and the 404 suggestions. It deserves the same design care as the human reader: stable keys, consistent structure, short exact descriptions, and a helpful answer when it asks for something that does not exist. The reader you never see is designed for too.

### IV.3 · Color strategy: paper, ink, one violet

The page is paper (`#FAFAF8`) or night (`#111016`). Everything above it is ink at three fixed strengths.

One color is allowed to speak: violet, LaRecipe's heritage purple (`#787AF6`) pushed deeper so it carries white text with confidence. It appears only where something is in a state: the active page, a focused control, a link, a highlighted line, a fresh page, the single primary action. Its rarity is the point. A reader should be able to scan a LaRecipe page and find everything that is "live" by color alone.

Feedback hues (success, warning, danger) color labels and icons, never surfaces.

On a team's docs, their accent takes violet's place. Everything else holds.

### IV.4 · Typography philosophy

LaRecipe shares its type with Nashra, deliberately. Inter with `ss01` and `cv11` for English, Thmanyah Sans for Arabic, JetBrains Mono for anything a machine produced. Products from the same maker should feel related the way two instruments from the same workshop do.

LaRecipe tells itself apart in two ways:

- **Mono labels.** Group names, ticket headers, paths, hashes, timestamps and keyboard hints are set in small uppercase mono, like print on a ticket. The rule: machine-made things are mono, human writing is sans.
- **Firm headings.** Docs are scanned before they are read, so headings ship at 600 with tight tracking. They are signposts.

Arabic is set in Thmanyah Sans at natural tracking with more line height. Latin words, numbers and code inside Arabic text stay in Inter and stay left to right.

### IV.4b · The head-chef standard

Before any rule about duration or easing, the thing being designed for is a feeling: using LaRecipe should feel like eating in a kitchen where the head chef checks every plate.

What a great head chef actually does at the pass:

- **They know every order.** The context is already there. LaRecipe knows which pages a change affects before anyone asks, and the agent starts with that list.
- **They catch the wrong plate before it leaves.** Validation, broken links, missing references and unreviewed agent text are stopped at the pass, never discovered by a reader.
- **They call it once, plainly.** An error says what is wrong and what to do next, in one sentence. No red walls, no blame, no stack traces for readers.
- **They keep the kitchen out of the dining room.** Readers never see machinery: no spinners on navigation, no layout jumps, no "AI is thinking" theatre.
- **They credit the cook.** Provenance names who and what made the change. An agent's work is attributed to the agent and approved by a person, in the open.
- **They keep the rhythm.** Service is fast or it fails. Pages arrive instantly, search answers as you type, the palette opens before your hand leaves the keys.

**When you're stuck, ask:** what would the head chef do here? If the answer is "it would already be handled", build that instead of a feature. If it is "they'd say nothing", stay silent.

**The test for anything we ship:** does this make the reader trust the page and the developer trust the agent? If it only makes LaRecipe look clever, it does not ship.

### IV.5 · Motion personality

The principle is **precision**. Motion moves like good machinery: a fast start, an exact stop, no overshoot, no bounce. It confirms what the user did; it never announces itself.

**Four canonical interactions.**

| Interaction | Duration | Curve | What changes |
| --- | --- | --- | --- |
| Hover | 120ms | ease-pass | Color and border. Never layout, never lift. |
| Focus | 120ms | ease-pass | The violet ring appears. It never pulses. |
| State | 180ms | ease-pass | Selection, tabs, toggles, the article swap. |
| Panel | 240ms | ease-pass | Palette, Ask panel, menus, sheets. |

`ease-pass` is `cubic-bezier(0.2, 0, 0, 1)`. `prefers-reduced-motion` collapses everything to an instant change. Non-negotiable.

**Five branded moments.**

1. **The print.** When a ticket arrives (a new draft in the admin review queue, a citation in an Ask answer, a page updated while you read it), it slides down 8 px from above as if it just came out of the printer, and its perforation draws from inline-start to inline-end. 240ms, once. The signature motion.
2. **The swap.** Client navigation replaces only the article: a 160ms crossfade inside a still frame. The sidebar, header and rail never move. The reader learns the rhythm in a minute and forgets it forever.
3. **The palette.** `⌘K` opens the palette from the search field's position (a 0.98 to 1 scale with a fade, 180ms). Results are there before the eye lands.
4. **The copy.** A copy button's icon becomes a check and its label reads "Copied" in mono for 1.2 seconds. No toast, no confetti.
5. **The first page.** After `larecipe:install`, the first page loads with its first ticket, `TICKET 0001 · Your first page`, printing into the rail. The welcome, said once.

The waiting state is three mono dots printing left to right, one every 160ms. Nothing under 300ms shows it.

### IV.6 · Imagery and illustration

**The product is shown with the real product.** Screenshots of real LaRecipe sites, real terminal sessions with real output, real diffs. Never a wireframe, never a mockup with grey bars, never lorem ipsum. If the real asset is not ready, ship a clearly labelled placeholder and say so.

**Diagrams are schematic.** Hairline boxes, mono labels, one violet path that shows "the change" moving through the system. Closer to a railway diagram than to a flat illustration. The recurring diagram is the loop from `PRODUCT.md`: code, ticket, review, page, agent.

**The motif is the rail.** Tickets hanging in a row: the changelog page is literally a rail of tickets, and a page's history is its own short rail.

**The landing page is a docs page.** LaRecipe's own site opens on the docs Introduction, not on a marketing hero. Its one cinematic element is the demo video of the maintainer explaining the product, set directly under the lead in a 16:9 frame with a hairline border and `rounded-lg` corners, mono caption beneath (`DEMO · 2:40`). Until the video exists, the frame holds a labelled placeholder ("Demo video coming soon"), never a stock clip or a fake.

**Never:** stock photos, AI-generated faces, chef-hat clipart, cartoon cooks, emoji, 3D blobs, gradient meshes.

### IV.7 · Data visualization

LaRecipe does not ship a traffic dashboard. Reading analytics belong to the tools teams already use (GA4, Plausible, Fathom, PostHog), wired in through integrations and Laravel events, including the agent requests that client-side analytics never see.

What LaRecipe shows is about freshness, not traffic: the stale-page count in `larecipe:affected` and `larecipe:validate` output, and in the admin UI a list of pages with their fresh dot and last ticket. Where a figure appears, it follows the same restraint: one violet value, mono labels, no charts for the sake of charts.

Mono axis labels at three points, no grid, no rainbow, no animation on entry. Charts earn loudness through the data.

### IV.8 · Customer-facing surfaces

When a team publishes docs with LaRecipe, the reader sees the team's docs.

- The team's accent replaces violet; their logo replaces the mark; their fonts may replace Inter.
- The structure and the signature elements stay: the provenance line, the Last change ticket, the perforation, mono labels, page actions for agents. These are features, not branding.
- Kitchen words stay out of the copy by default: "Updated", "Last change", "Page not found". The ticket is a shape there, not a word.
- LaRecipe's presence is one optional, small "Built with LaRecipe" credit in the footer: the ticket glyph and the name, in ink.

LaRecipe is felt in the structure and invisible in the branding.

### IV.9 · The atomic unit: the ticket

LaRecipe's atomic visual unit is the **ticket**: a small card that records one change, with a perforated header and a bitten bottom edge.

It is the same idea everywhere:

- **A page is a stack of tickets.** Its history is the tickets that changed it; the Last change card shows the top one.
- **The changelog is the rail.** Tickets in a row, newest first.
- **The review queue is the pass.** In the admin UI (database source), agent drafts arrive as tickets and leave published or rejected. In the repository source, the pull request is the pass.
- **Ask citations are tickets.** Compact, one line, pointing at the page that answers.
- **The CLI prints tickets.** `TICKET 0142 · fired 14:02 · 2 pages`.

The bitten edge comes from LaRecipe's original mark, the recipe card with a scalloped bottom. The mark is that card redrawn as a ticket: small square-ish corners, four bites along the bottom, three bars inside. It is the only other place the bitten edge appears.

**Guardrails, so it doesn't rot.**

1. **A ticket carries real data.** Never a decorative card with a torn edge. If there is no change behind it, it is not a ticket.
2. **The edge means a change.** Do not put the bitten edge on ordinary cards, images or sections.
3. **One accent, held.** Tickets are surface and ink; only their action link and fresh dot are violet.
4. **The mark is never violet,** never animated, never rebuilt out of tickets. Ink on paper, paper on night.

## V · The recurring LaRecipe moves

The checklist for "does this feel like LaRecipe?"

1. **The ticket.** Changes are shown as tickets with provenance.
2. **The perforation.** Metadata is separated from content by a dotted rule, not a heavy line.
3. **Mono labels.** Machine-made things look printed.
4. **One violet.** Color only where something is in a state.
5. **The fresh dot.** Recently changed pages say so, in the page and in the sidebar.
6. **Agents in plain sight.** Page actions for agents sit next to the table of contents, not in a hidden menu.

A new screen with none of these is not LaRecipe yet.

## VI · The bilingual approach

Functionally native, visually identical.

- Arabic is a peer rendering, not a translation toggle: the same components, mirrored with logical properties, the same motion reflected.
- Thmanyah Sans sets Arabic; Inter keeps Latin words and numerals; JetBrains Mono keeps code. Code blocks, commands, versions and hashes stay left to right inside RTL pages.
- Directional icons (arrows, chevrons, "open" glyphs) mirror. The search icon, the copy icon and the mark do not.
- Mono uppercase labels have no Arabic equivalent, so Arabic UI labels become sans 500 at a slightly larger size.
- "LaRecipe" stays Latin in Arabic UI. It is a name.

Every surface is checked in `dir="ltr"` and `dir="rtl"` before it ships. The brand canvas includes an Arabic rendering of the docs page for exactly this reason.

## VII · What LaRecipe is not

We are not:

- A hosted docs platform
- A CMS
- A static site generator
- A chatbot

We do not ship:

- Gradients, glassmorphism, sparkles, neon
- A chat bubble floating over the content
- Callouts with colored side stripes or tinted washes
- Hero-metric templates and identical icon-card grids
- Chef hats, cartoon cooks or kitchen puns on customer docs
- Font Awesome, progress bars in docs, or emoji as UI
- Shadows on cards, or more than one filled violet button per screen

When in doubt, the impeccable absolute bans apply.

## VIII · The 18-month horizon

Mid-2027.

A maintainer approves a docs ticket on her phone and goes to bed. A support engineer reads a page in Arabic that changed yesterday with the release. An agent at 3am reads the Markdown and gets it right. A tech lead deletes the separate docs repo, because the docs now live in the app and keep up on their own.

Somewhere, a reader notices the small "Built with LaRecipe" credit under a docs page that has never once been out of date, clicks it, and lands on a site that is calm, exact and unmistakably the same instrument.

That is the horizon. A docs engine that earns trust by checking every plate, visible in its tickets and its precision, and almost nowhere else.

## IX · Implications for the next 90 days

A punch list, not a roadmap.

1. **Build the ticket first.** The ticket component, the perforation and the print motion, prototyped together with the article-adoption technique from `ARCHITECTURE.md` §7.4. They are the identity and the riskiest UI.
2. **Draw the mark properly.** The ticket mark as a production vector at 16 to 512 px, a simplified favicon (two bars), social and OG templates, light and dark.
3. **Confirm font licensing for distribution.** Inter and JetBrains Mono are OFL. Thmanyah Sans is free for commercial use; confirm that bundling it inside an MIT package is allowed, or ship it as an optional download the installer fetches.
4. **Make the "Pass" syntax theme.** A Phiki theme for the dark slab using the syntax colors in `DESIGN.json`, checked for contrast.
5. **Write the agent-surface style guide.** Markdown projection conventions, MCP tool descriptions, validation messages and 404 suggestions, held to the same voice as the UI.
6. **Record the demo video and ship the Introduction page.** The docs are the landing page; the video slot sits under the lead and stays a labelled placeholder until the recording is ready.
7. **Earn AFDocs grade A on the demo site** and keep it in CI.

## A closing note

This document is alive. The pass, the ticket and the head-chef standard are the choices we are committing to today. They will sharpen as they meet real readers, real agents and real teams. When they sharpen, this document updates.

What does not change is the test. A new design comes up; we ask: would the head chef let this plate leave? Does it make the reader trust the page? Does it work in Lisbon at 11pm, in Riyadh at 2pm, and for the agent at 3am? Does it reduce to one of the LaRecipe moves, or does it drift?
