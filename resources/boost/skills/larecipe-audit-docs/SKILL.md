---
name: larecipe-audit-docs
description: Review a whole LaRecipe doc set for stale pages, missing sources, weak descriptions, undocumented routes, commands, models and events, and broken samples, then produce a prioritized list of fixes. Use when asked to audit, review or health-check the docs, or before a release.
license: MIT
compatibility: A Laravel app with larecipe/larecipe installed, so php artisan larecipe:* works. The larecipe MCP server is recommended.
metadata:
  author: larecipe
  version: "3.0"
---

# Audit LaRecipe docs

An audit answers one question: where are these docs wrong, missing or weak, in order of how much it hurts readers? Gather facts with the deterministic commands first, then read. Report before you fix, unless the user asked you to fix.

## 1. Gather the facts

Run these and keep the output:

```bash
php artisan larecipe:validate --json
php artisan larecipe:affected --docs-version=all --format=json
php artisan larecipe:sources:suggest --json
```

- `validate` lists broken links and anchors, schema errors, unknown components, missing snippet regions, live references that no longer resolve, orphan pages and docs migration conflicts. Every issue has a stable `rule` and often a `fix`.
- `affected` with no range lists `drift`: pages whose sources changed after the page was last updated, with the commit that made them stale.
- `sources:suggest` lists pages without `sources:` and the files they mention. A page without sources is invisible to drift detection.

## 2. Look for what is missing

Compare the app's public surface with the docs (use `search_docs` and `list_pages` on the `larecipe` MCP server, or read the docs folder):

- **Routes:** `php artisan route:list --json`. Public and API routes with no page, or pages describing routes that no longer exist.
- **Commands:** `php artisan list --format=json`. The app's own commands (not the framework's) with no page.
- **Models and events:** `app/Models` and `app/Events`. Core models or events users depend on with no page.
- **Configuration:** keys in the app's config files that change behavior users see, with no mention. Mention key names only, never values.

## 3. Read for quality

For each page, check:

- `description` exists, is one sentence and says what the reader gets (not a repeat of the title).
- The first paragraph answers what the page is about. See [writing style](references/writing-style.md).
- Pasted values that should be live references (`<Route>`, `<Artisan>`, `<Model>`, `<Event>`). See [live references](references/live-references.md).
- Code samples that no longer match the code; prefer snippet includes (`<<< @/path#region`).
- Components and frontmatter keys that do not exist. See [the component catalog](references/components.md) and [frontmatter](references/frontmatter.md).
- Configuration or environment values, secrets or customer data on any page. **Always P1.**
- Navigation: pages missing from `navigation.json`, empty groups, confusing order. See [navigation](references/navigation.md).

## 4. Report

Produce a prioritized list. Each item names the page (or the missing page), the problem, the evidence and the fix:

- **P1, wrong or unsafe:** validation errors, drifted pages whose content is now false, secrets or settings values in docs.
- **P2, missing:** undocumented public surface, pages without `sources:`.
- **P3, weak:** missing or vague descriptions, pasted values, navigation and style issues.

Keep it scannable: one line per item, grouped by priority, with counts at the top.

## 5. Fixing

Fix only what the user asks for. Use `larecipe-update-docs` for drifted pages and `larecipe-write-docs` for missing pages. Never write configuration or environment values into docs; when you find them, remove them and name the key instead. On a database set, write through the LaRecipe MCP tools, never rows or SQL (see [database sets](references/database-sets.md)). Run `php artisan larecipe:validate` when done, and follow the review mode in `AGENTS.md`.
