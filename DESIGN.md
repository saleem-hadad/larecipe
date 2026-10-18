---
name: LaRecipe
description: Living documentation for Laravel apps, expressed as the pass, the counter where every change is checked before it reaches a reader.
colors:
    paper: '#FAFAF8'
    ink: 'rgba(28, 27, 34, 0.92)'
    ink-muted: 'rgba(28, 27, 34, 0.66)'
    ink-faint: 'rgba(28, 27, 34, 0.44)'
    surface: '#F2F1F4'
    surface-2: '#EAE9ED'
    hairline: '#E7E6EA'
    hairline-strong: '#D6D5DB'
    violet: 'oklch(0.55 0.2 279)'
    violet-soft: 'oklch(0.955 0.025 279)'
    violet-foreground: '#FFFFFF'
    code: '#17161C'
    code-foreground: '#E8E7EE'
    night: '#111016'
    success: 'oklch(0.56 0.13 155)'
    warning: 'oklch(0.66 0.15 70)'
    danger: 'oklch(0.56 0.2 25)'
typography:
    display:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '56px'
        fontWeight: 600
        lineHeight: 1.0
        letterSpacing: '-0.04em'
    h1:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '40px'
        fontWeight: 600
        lineHeight: 1.1
        letterSpacing: '-0.035em'
    h2:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '24px'
        fontWeight: 600
        lineHeight: 1.25
        letterSpacing: '-0.02em'
    h3:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '18px'
        fontWeight: 600
        lineHeight: 1.35
        letterSpacing: '-0.01em'
    lead:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '18px'
        fontWeight: 400
        lineHeight: 1.6
        letterSpacing: 'normal'
    prose:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '16px'
        fontWeight: 400
        lineHeight: 1.75
        letterSpacing: 'normal'
    ui:
        fontFamily: "'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '14px'
        fontWeight: 400
        lineHeight: 1.45
        letterSpacing: 'normal'
    label:
        fontFamily: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace"
        fontSize: '11px'
        fontWeight: 500
        lineHeight: 1.2
        letterSpacing: '0.1em'
    meta:
        fontFamily: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace"
        fontSize: '11.5px'
        fontWeight: 400
        lineHeight: 1.5
        letterSpacing: 'normal'
    code:
        fontFamily: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace"
        fontSize: '13.5px'
        fontWeight: 400
        lineHeight: 1.7
        letterSpacing: 'normal'
rounded:
    xs: '3px'
    sm: '4px'
    md: '6px'
    lg: '10px'
    full: '9999px'
spacing:
    xs: '4px'
    sm: '8px'
    md: '16px'
    lg: '24px'
    xl: '40px'
    2xl: '56px'
components:
    button-primary:
        backgroundColor: '{colors.violet}'
        textColor: '{colors.violet-foreground}'
        rounded: '{rounded.md}'
        padding: '0 14px'
        height: '34px'
    button-secondary:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        padding: '0 14px'
        height: '34px'
    button-outline:
        backgroundColor: 'transparent'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        padding: '0 14px'
        height: '34px'
    button-ghost:
        backgroundColor: 'transparent'
        textColor: '{colors.ink-muted}'
        rounded: '{rounded.md}'
        padding: '0 10px'
        height: '34px'
    input:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        padding: '0 12px'
        height: '36px'
    code-block:
        backgroundColor: '{colors.code}'
        textColor: '{colors.code-foreground}'
        rounded: '{rounded.md}'
        padding: '14px 16px'
    callout:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        padding: '16px 18px'
    ticket:
        backgroundColor: '{colors.surface}'
        textColor: '{colors.ink}'
        rounded: '{rounded.md}'
        padding: '16px 16px 26px'
    badge:
        backgroundColor: 'transparent'
        textColor: '{colors.ink-muted}'
        rounded: '{rounded.sm}'
        padding: '2px 6px'
---

# Design System: LaRecipe

## 1. Overview

**Creative North Star: "The Pass"**

In a restaurant kitchen, the pass is the counter where the head chef checks every plate before it leaves. Tickets hang above it. Nothing reaches a guest unchecked. LaRecipe is that counter for documentation: code changes fire tickets, agents prepare the docs, a human signs them off, and the reader receives a page that says exactly when and why it changed.

The interface is an instrument for trust. Paper and ink, one violet that only ever means state, mono labels for anything a machine produced, and a clean sans for anything a person reads. Structure is carried by whitespace, a hairline, and a perforated rule borrowed from a kitchen ticket. Corners stay small and exact. Light is thermal paper under a pass lamp; dark is the kitchen after service, the screen the only light left. Both are first-class. Arabic is a peer rendering, not a translation toggle.

The system rejects the generic AI-docs look: purple-to-blue gradients, sparkles, glassmorphism, floating chat bubbles that cover the content. It rejects the default docs template that makes every site recognizable as its vendor's. It rejects LaRecipe v2's dated chrome (Font Awesome, heavy shadows) but keeps the v2 details readers knew: the violet ticket logo, the navigation switch, the reading line along the top, the back-to-top button and the violet top edge on code. Where anything only decorates, LaRecipe states facts.

**Key characteristics:**

- Paper and ink first; violet is a state, never a decoration.
- Mono for what machines produced (labels, metadata, tickets, code); sans for what people read.
- The ticket is the signature shape: a perforated header and a bitten bottom edge.
- Provenance is designed into the page, not appended to it.
- Corners are small (4 / 6 / 10 px). Precision over softness.
- Flat at rest. Shadows only on things that float.
- Motion is mechanical and exact (120 / 180 / 240 ms), never bouncy.
- Bilingual and RTL-native from the first line of CSS.

This system is the default theme of every LaRecipe site. Teams retheme it through tokens (accent, fonts, radius, logo); the structure and the signature elements stay.

## 2. Colors

A near-monochrome paper and ink system with a slight violet cast in the neutrals, one saturated violet rationed for state, and three feedback hues that appear only as labels.

### Primary

- **Violet** (`oklch(0.55 0.2 279)` light, `oklch(0.74 0.14 279)` dark): The heritage LaRecipe purple (`#787AF6`) pushed deeper so white text on it clears 4.5:1. It carries the one primary action per screen (Ask the docs), focus rings, the active navigation item, links in running text, highlighted code lines, step numbers and the fresh dot. It is never a background wash, never a gradient, and never the mark.
- **Violet soft** (`oklch(0.955 0.025 279)` light, `oklch(0.28 0.06 279)` dark): Selected and active backgrounds only (the current sidebar item, which also carries a 2 px violet edge at its start; a selected search result).

### Neutral

- **Paper** (`#FAFAF8` light) and **Night** (`#111016` dark): The canvas. Header and article share it.
- **Sidebar paper** (`#F3F3F0` light, `#16151C` dark): One step deeper than the canvas, so the navigation reads as its own column, as in v2. Hover in the sidebar is `#E9E8E5` light, `#222129` dark.
- **Ink** (`rgba(28, 27, 34, 0.92)` light, `rgba(244, 243, 248, 0.92)` dark): Headings and body text.
- **Ink muted** (`0.66` alpha light, `0.64` dark): Secondary text, labels, metadata. Clears 4.5:1 on its canvas.
- **Ink faint** (`0.44` alpha light, `0.42` dark): Placeholders, disabled states and decoration only. Never carries information.
- **Surface** (`#F2F1F4` light, `#1B1A21` dark): Callouts, tickets, inputs, the search field. One measured step off the canvas.
- **Surface 2** (`#EAE9ED` light, `#24232B` dark): Hover and pressed states.
- **Hairline** (`#E7E6EA` light, `#26252D` dark) and **Hairline strong** (`#D6D5DB` light, `#34333C` dark): Borders, dividers, the perforation dots, hover borders.
- **Code** (`#17161C` light, `#0B0A0F` dark): Code blocks sit on a dark slab in both modes, with a 2 px violet top edge (a code group carries one edge for the whole group). Syntax colors: keywords `#B4A7FF`, strings `#9FDDB4`, comments at 42% foreground, functions `#F2C97D`, numbers and constants `#8FD3FF`.

### Semantic (labels only)

- **Success** (`oklch(0.56 0.13 155)`), **Warning** (`oklch(0.66 0.15 70)`), **Danger** (`oklch(0.56 0.2 25)`). Each lightens in dark mode (`L 0.78 to 0.82`). They color a label, an icon or a diff gutter. They never fill a panel.

### Named Rules

**The Violet-Is-State Rule.** Violet means something is active, focused, selected, linked, fresh or the single primary action. If an element is violet and none of those is true, it is wrong. One filled violet button per screen.

**The Labels-Not-Washes Rule.** Callouts, badges and alerts get their meaning from a colored label and icon on a neutral surface. No tinted backgrounds, no colored side stripes.

**The Measured-Ink Rule.** Text uses the fixed alpha ladder (0.92 / 0.66 / 0.44), never pure black or white and never a parallel gray. Information never sits at the faint step.

**The Customer-Accent Rule.** On a team's docs, their accent replaces violet through the `accent` token, and soft and dark variants are derived in OKLCH so contrast holds. The structure, the ticket and the perforation stay. LaRecipe's mark appears only in the optional "Built with LaRecipe" credit.

## 3. Typography

**Sans:** Inter (variable), with `font-feature-settings: 'ss01', 'cv11'`, the same face and features as nashra.ai. Stack: `'Inter Variable', 'Inter', 'Thmanyah Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`.
**Arabic:** Thmanyah Sans (400, 500, 700), self-hosted. It sits after Inter in the stack, so Arabic glyphs render in Thmanyah while Latin words, numerals and code identifiers inside Arabic text stay in Inter.
**Mono:** JetBrains Mono (400, 500) for code, labels, metadata, tickets and keyboard hints.

All three faces are shared with Nashra, a deliberate family resemblance between products from the same maker. LaRecipe tells itself apart through mono labels, firm headings, the ticket and violet.

**Character:** Exact, legible, a little mechanical. Headings are firm signposts for scanning. Prose has air. Labels look printed.

### Hierarchy

- **Display** (600, 56 px, line-height 1.0, tracking −0.04em): Rare brand moments (the 404, social cards). LaRecipe's own landing page is a docs page and uses H1.
- **H1** (600, 40 px, 1.1, −0.035em): The page title. One per page.
- **H2** (600, 24 px, 1.25, −0.02em): Sections. They feed the table of contents and search records.
- **H3** (600, 18 px, 1.35, −0.01em): Subsections.
- **Lead** (400, 18 px, 1.6, ink muted): The page description under the H1. Capped at 640 px.
- **Prose** (400, 16 px, 1.75): Body text. Measure 68 to 72 characters (max-width 720 px).
- **UI** (400 or 500, 14 px, 1.45): Navigation, buttons, inputs, menus.
- **Label** (JetBrains Mono 500, 11 px, tracking 0.1em, uppercase): Navigation group names, section labels, ticket headers, code block titles.
- **Meta** (JetBrains Mono 400, 11.5 px): Provenance lines, paths, hashes, reading time.
- **Code** (JetBrains Mono 400, 13.5 px, 1.7): Code blocks. Inline code at 0.875em on surface with a 3 px radius.

### Named Rules

**The Firm-Heading Rule.** Docs headings ship at 600 with negative tracking. Readers scan docs before they read them; headings are signposts, not decoration. (Nashra's quiet 400 headings suit a writing tool. LaRecipe is a reference.)

**The Machine-Is-Mono Rule.** Anything a machine produced or a machine reads (paths, hashes, commands, timestamps, ticket numbers, keyboard shortcuts, version ids) is set in mono. Anything a person wrote for a person is set in sans.

**The Arabic-Breathes Rule.** Arabic text never inherits negative tracking. It runs at `letter-spacing: normal`, prose line-height 1.8, and Thmanyah's 700 stands in wherever the Latin scale asks for 600. Mono labels in Arabic UI become sans 500 at 12.5 px, because uppercase tracking has no Arabic equivalent.

**The Numerals-Stay-LTR Rule.** Inside RTL text, numerals, versions, hashes and commands stay left to right (`dir="ltr"` on the run). Use `tabular-nums` for figures that align or update.

## 4. Elevation

Flat at rest. Depth comes from the surface step and the hairline. Shadows belong only to things that float above the page: the command palette, the Ask panel, popovers, menus and the docked Ask bar.

### Shadow Vocabulary

- **`shadow-float`** (`0 12px 32px -8px rgba(17, 16, 22, 0.22), 0 0 0 1px var(--hairline)`): The docked Ask bar, popovers, menus.
- **`shadow-overlay`** (`0 30px 80px -20px rgba(17, 16, 22, 0.35), 0 0 0 1px var(--hairline)`): Command palette, Ask panel, dialogs.
- **`ring-focus`** (`0 0 0 2px var(--paper), 0 0 0 4px color-mix(in oklch, var(--violet) 45%, transparent)`): Keyboard focus on every interactive element.

### Named Rules

**The Flat-At-Rest Rule.** Cards, callouts, tickets and code blocks have no shadow at rest or on hover. Interactive cards show hover with `hairline-strong`, never a lift.

**The Shared-Canvas Rule.** Header, article and table of contents share the canvas. Boundaries are a single hairline. The sidebar is the one tonal panel: sidebar paper, one step deeper, running to the window's edge.

## 5. Components

Every interactive component ships the full state set: default, hover, focus-visible, active, disabled and loading. Transitions run at 120 ms for hover and 180 ms for state.

### Signature: The Ticket

The unit of change. A surface card with a mono header row (`TICKET 0142` left, time right), a perforated rule, a one-sentence summary of what changed and why, mono provenance (commit or revision, pages touched, lines added and removed, who signed it off) and a single violet action. Its bottom edge is bitten: a row of 5 px half-circles at a 12 px pitch, made with a CSS mask, echoing the scalloped edge of the original LaRecipe recipe card.

Where it appears: the "Last change" card in the page rail, changelog entries, the admin review queue (database source), agent draft notifications, Ask AI citations (as a compact single-line ticket), and LaRecipe's own CLI output (as text: `TICKET 0142 · fired 14:02`).

The ticket number is the source's revision number when it has one (database source), otherwise the count of commits touching the docs root, otherwise the short commit hash.

### Signature: The Perforation

A dotted rule (1.3 px dots at an 8 px pitch, hairline color) that separates metadata from content: under a page header, inside tickets, between the article and its footer. It replaces most solid dividers.

### Signature: The Fresh Dot

A 6 px violet dot that means "changed recently" (default: within 7 days). It sits before the provenance line under the H1 and after a sidebar item whose page changed. It is the only decoration allowed in the sidebar, and it is a state.

### Buttons

- **Shape:** `rounded-md` (6 px), height 34 px on desktop and 40 px on touch, 14 px / 500 text, 8 px icon gap.
- **Primary:** violet fill, white text. One per screen (Ask the docs in the header).
- **Secondary:** surface fill; hover moves to surface 2.
- **Outline:** transparent with a hairline border; hover to hairline strong.
- **Ghost:** transparent, ink muted; hover to surface. Toolbar and icon actions (icon buttons are 34 px square with an `aria-label`).

### Inputs and search

- **Style:** surface background, hairline border, `rounded-md`, 36 px tall, 12 px inline padding, placeholder at ink faint.
- **Focus:** border becomes violet plus `ring-focus`.
- **Search field (header):** 420 px, leading search icon, trailing `⌘K` keycap. Opens the command palette; it is a trigger, not a live field.

### Navigation

- **Header:** the LaRecipe logo (or the team's) and the docs name, then the navigation switch (a 34 by 20 px pill: violet with the thumb at the end while the sidebar shows, hairline strong when hidden; wide screens only, remembered per reader), then the version menu. At the end, one icon button switches light and dark in a click (moon offers dark, sun offers light; with no choice made the page follows the system).
- **Reading line:** a 2 px violet line along the top of the header that fills as the page scrolls, on a violet soft track.
- **Back to top:** a 44 px round violet button in the end corner, shown after 600 px of scrolling; it returns focus to the page title.
- **Sidebar:** 248 px on sidebar paper, mono labels for groups, 14 px items 30 px tall with a 4 px radius. Active item: a 2 px violet edge at its start, violet soft background, violet text, 500 weight. No icons by default (frontmatter `icon` allows one). Scrolls the active item into view on load.
- **Table of contents:** 240 px rail, 13 px links, active link in ink at 500 with scroll-spy. Followed by "For agents" page actions, then the Last change ticket.
- **Breadcrumb:** mono label above the H1 (`GET STARTED / INSTALLATION`).
- **Previous and next:** two outline cards at the end of the article with mono direction labels.

### Page header

Breadcrumb label, H1, lead, then the provenance meta line: fresh dot, "Updated 2h ago by Claude Code", short hash, reading time. A perforation follows.

### Code blocks

- Dark slab in both modes, `rounded-md`.
- Header strip (36 px) with a mono uppercase title or language and a copy button.
- Tabs for code groups: mono uppercase triggers with a 2 px violet underline on the active one.
- Highlighted lines: a violet band at 14% alpha with a 2 px violet inline-start marker. Diff lines: success and danger labels in the gutter only.
- Line numbers in ink faint, optional.

### Callouts

Surface background, hairline border, `rounded-md`, 16 px by 18 px padding. A mono label with an icon in the type's color: NOTE (violet), TIP (success), WARNING (warning), DANGER (danger), IMPORTANT (violet). Body in prose at 15 px. No side stripe, no tinted fill.

### Steps

Mono step numbers (`01`, `02`) in violet, 28 px column, H2 or H3 titles, content aligned to the title. No connecting lines.

### Cards

Hairline border, `rounded-md`, 20 px padding, title 15 px / 600, description in ink muted. Linked cards change border to hairline strong on hover and show a trailing arrow. No shadows, no icons in colored circles.

### Video

A 16:9 frame with a hairline border and `rounded-lg` corners, poster image, a centered ink play button (56 px circle, paper icon), and a mono caption below (`DEMO · 2:40`). Click to load: no third-party player loads until the reader asks. With no source, it renders a labelled placeholder frame ("Demo video coming soon") on surface, never an empty box. In agent Markdown it becomes a link with its title and duration.

### Badges and keycaps

- **Badge:** mono 10.5 px uppercase, 4 px radius, outline or surface fill, 2 px by 6 px padding. Tones follow the semantic labels.
- **Keycap:** mono 10 px, hairline border, 3 px radius.

### Command palette

Centered, 640 px, `shadow-overlay`, `rounded-lg`. A query field on top; results appear as you type from the in-browser fuzzy index (no network round trip), grouped under mono labels (Pages, Sections, Actions), with matched characters in ink at 600. Each result shows a title and a mono path. The first row for any query is "Ask AI about '…'" with a violet ASK label. Fully keyboard driven.

### Ask the docs

- **Ask bar:** docked at the bottom of the article column on desktop, `shadow-float`, a mono violet ASK label, the input, a `⌘I` keycap and an ink send button. On mobile it becomes a button in the header.
- **Ask panel:** a 420 px side sheet, `shadow-overlay`. Answers stream as prose. Citations render as compact tickets (mono path, page title). It always shows its sources and says plainly when the docs do not cover the question.

### Page actions ("For agents")

Copy as Markdown, View as Markdown, Open in Claude, Open in ChatGPT, Open in Cursor, Connect MCP. Ghost rows in the rail on desktop; a split button (Copy page plus a menu) under the H1 on mobile. About six options, never eighteen.

### Loading

The article never shows a spinner: client navigation swaps prefetched content. Panels that wait show a single line of three mono dots that print left to right (`· · ·`), timed like a ticket printer (one dot every 160 ms). Nothing under 300 ms shows a loader.

### Not found

On LaRecipe's own site, the 404 is "86'd", kitchen slang for an item that is off the menu, with up to five similar pages. On customer docs the default copy is plain ("Page not found") with the same suggestions. `.md` misses return the same suggestions in Markdown.

## 6. Do's and Don'ts

### Do

- **Do** keep violet for state: focus, selection, the active page, links, freshness and the one primary action.
- **Do** set machine-made things in mono and human writing in sans.
- **Do** show provenance on every page: when it changed, what changed it, who signed it off.
- **Do** carry structure with whitespace, the hairline and the perforation before reaching for a box.
- **Do** keep corners at 4, 6 or 10 px.
- **Do** check every surface in light, dark, `dir="ltr"` and `dir="rtl"`, and with JavaScript disabled.
- **Do** keep code left to right inside RTL pages, and numerals readable.
- **Do** consume icons through one `Icons` namespace (Lucide, 1.75 stroke, 16 px default).
- **Do** let a team's accent, fonts and logo replace LaRecipe's on their docs.

### Don't

- **Don't** use gradients, glassmorphism, sparkles or neon. The generic AI-docs look is the primary anti-reference.
- **Don't** tint callouts or add colored side stripes. Labels carry meaning.
- **Don't** put more than one filled violet button on a screen.
- **Don't** draw the mark in violet. Ink on paper, paper on night.
- **Don't** add shadows to cards, callouts, tickets or code blocks.
- **Don't** use pure black or white for text, or information at the faint step.
- **Don't** float a chat bubble over the content. Ask lives in the header, the palette and the docked bar.
- **Don't** use kitchen slang on customer docs by default. "Ticket" is a shape there; "fired" and "86'd" belong to LaRecipe's own surfaces.
- **Don't** use em dashes in copy.
- **Don't** add decorative motion. Transitions convey state; `prefers-reduced-motion` collapses them.
