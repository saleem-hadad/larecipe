# Writing style

Write for people first. Agents read every word of the Markdown twin, so they are served by anything you write; people skim, and stop as soon as they have their answer. Give them that answer fast.

## Easy first, depth on demand

This is LaRecipe's first writing rule.

- **The common case first, in few words.** A page opens with the shortest path that works for most readers: what it is, when to use it, the one command or snippet. Many readers stop there, and that is success.
- **Depth on demand.** Options, edge cases, internals and reference tables come after the basics: lower on the page under a plain heading, folded into an `<Accordion>`, or on their own page linked at the end. Folding hides nothing from agents; the Markdown twin keeps it all.
- **Easy pages before hard ones.** A doc set reads in order: getting started, then everyday tasks, then advanced topics and reference. Niche or expert material (upgrading from an old version, internals, tuning) never sits on the introduction or the first pages; link to it from where an expert will look.
- **Short pages.** A task page longer than about two screens usually holds two topics: split it. Say a thing once and link to it, instead of repeating it on every page.
- **Cut, then cut again.** No preamble that restates the title, no "in this guide we will", no summary of what the reader just read.

## Voice

- **Answer first.** The first paragraph says what the page is about and when you need it. The reasoning comes after.
- **Short sentences, plain words.** One idea per sentence, one topic per paragraph. Prefer "use" to "utilize", "to" to "in order to".
- **Second person, active voice.** "Run the installer", not "the installer should be run".
- **Precise.** Commands are copyable and correct. Numbers are real or absent. A claim you cannot back with the code does not belong on the page.
- **Calm.** No exclamation marks, no emoji, no hype ("simply", "just", "magic", "effortless", "powerful").

## Structure

- One H1 per page, matching `title`. Sections are H2, subsections H3; deeper than H3 usually means the page should split.
- Headings are short and specific: "Retry failed deliveries", not "Retries and more".
- Put prerequisites and warnings before the step they apply to, not after.
- Use numbered lists for steps that must happen in order, bullets for everything else.
- End a task page with what to read next, as a relative link.

## Code

- Every code block has a language (` ```php `, ` ```bash `).
- Show the smallest example that works. Take it from the real code or tests, or include it with a snippet (`<<< @/app/Billing/Invoice.php#refund`).
- Show commands as people type them: `php artisan orders:prune --days=30`.
- Use inline code for names a reader might search for: classes, methods, config keys, routes, files.

## Links

- Link to other pages with relative `.md` paths and anchors: `[retries](./webhooks.md#retries)`.
- Link text says where it goes: "see [webhook retries](./webhooks.md#retries)", not "click here".

## What to leave out

- Configuration and environment values, secrets, customer data. Name a config key; never print its value.
- Code internals on pages for product users.
- History ("we used to...") unless it is an upgrade guide.
- Anything the code does not do yet.
