---
name: larecipe-write-docs
description: Write new LaRecipe docs pages from this app's code. Use when asked to document a feature, an API, an artisan command or part of the codebase, to add a page or section to the docs, or to run LaRecipe onboarding (starter docs for product users, API consumers, package users or an internal team).
license: MIT
compatibility: A Laravel app with larecipe/larecipe installed, so php artisan larecipe:* works. The larecipe MCP server is recommended and required for database doc sets.
metadata:
  author: larecipe
  version: "3.0"
---

# Write LaRecipe docs

You are writing docs for this Laravel app. LaRecipe serves them to people as a website and to agents as Markdown, `llms.txt` and MCP. Write from the code in this repository, not from memory: every claim on a page should be something you read here.

## 1. Find where the docs live

Read the LaRecipe section of `AGENTS.md` (between `<!-- larecipe:start -->` and `<!-- larecipe:end -->`). It names each doc set, where it lives and the review mode. Then:

- **Repository set** (the default): pages are Markdown files in the docs folder, usually `docs/`. You create and edit files.
- **Database set**: pages are rows in the app's database. Never edit rows, never write SQL, never hand-write a migration. Use the LaRecipe MCP author tools (`write_page`, `move_page`, `update_navigation`); they apply the change locally and write the branch's docs migration. See [database sets](references/database-sets.md).

A versioned set keeps each version in its own folder (`docs/3.x/`). Write in the default version unless told otherwise. A set with several languages keeps the default language at the version root and the others in a folder per locale (`docs/ar/`).

## 2. Check what already exists

Search before you write. Use `search_docs` and `list_pages` on the `larecipe` MCP server, or list the docs folder. If a page already covers the topic, extend it instead of adding a second one. Two pages that half-cover a feature are worse than one that covers it well.

## 3. Read the code

Read the code the page describes before writing a word: routes, controllers, FormRequests, models, commands, events, jobs, config file structure, tests. Tests are often the clearest source of examples. Note the files you read: they become the page's `sources:`.

## 4. Write the page

- **Frontmatter.** Every page has `title`, `description` (one sentence that says what the reader gets) and `sources` (globs of the code it documents, from the project root). See [frontmatter](references/frontmatter.md).
- **Easy first, depth on demand.** Open with the common case in a few short lines: what it does, when to use it, the one command or snippet that works. Put options, edge cases and internals after that, in an `<Accordion>`, or on a linked page. People skim; agents still read everything in the Markdown twin. Short sentences, plain words. See [writing style](references/writing-style.md).
- **Real examples.** Code samples come from the real code. Prefer a snippet include (`<<< @/app/Billing/Invoice.php#refund`, which pulls the `#region refund` part of the file) over pasting code that will drift.
- **Live references.** Write `<Route name="orders.store" />`, `<Artisan command="orders:prune" />`, `<Model name="Order" />` or `<Event name="OrderShipped" />` instead of pasting a URI, a command signature or a column list. They read the running app, so they cannot go stale. See [live references](references/live-references.md).
- **Components.** Use only the components in [the component catalog](references/components.md). Anything else renders as plain text.
- **Links.** Link to other pages with relative `.md` paths (`[webhooks](./webhooks.md#retries)`), so they work on GitHub too.

### Never write secrets or settings

Never write configuration or environment values into docs: no `.env` values, API keys, tokens, passwords, hostnames, internal URLs, customer data or real email addresses. To mention a setting, write its name in inline code (`services.stripe.secret`) and say what it controls, never its value. There is no `<Config>` or `<Env>` component, on purpose.

## 5. Put it in the navigation

- Order pages from easy to advanced: getting started first, everyday tasks next, advanced topics and reference last. Never put expert or niche pages (upgrades from old versions, internals) at the top.
- When the version has a `navigation.json`, add the page there, in the right group. See [navigation](references/navigation.md).
- Without one, navigation comes from folders and frontmatter `order`: place the file in the right folder and set `order`.
- A database set changes navigation with the `update_navigation` MCP tool.

Set-level settings (name, theme, versions, locales) belong in `larecipe.json`, never in frontmatter. See [the manifest](references/manifest.md).

## 6. Check your work

1. Run `php artisan larecipe:validate` and fix every error it reports. Warnings about missing descriptions are worth fixing too.
2. If you added, removed or renamed pages and `AGENTS.md` carries a docs index, run `php artisan larecipe:agents-md`.
3. Follow the review mode from `AGENTS.md`:
   - `required`: show the user the docs diff and wait for approval before committing.
   - `auto`: commit the docs with the code.
4. Write one line that summarizes the docs change, for the commit message or the pull request.

Docs ship in the same push as the code they describe: Markdown files for a repository set, the docs migration for a database set.

## Onboarding

When asked to "run onboarding for audiences: ..." (usually from `php artisan larecipe:onboard`), follow [the onboarding workflow](references/onboarding.md). It holds the four audience presets: product users, API consumers, package users and an internal team.
