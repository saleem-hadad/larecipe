---
name: larecipe-update-docs
description: Update the LaRecipe docs pages a code change made stale, before you push. Use before every git push or pull request, when php artisan larecipe:affected lists stale pages, when the LaRecipe pre-push check held a push, or when translations fell behind their source page.
license: MIT
compatibility: A Laravel app with larecipe/larecipe installed, so php artisan larecipe:* works. The larecipe MCP server is recommended and required for database doc sets.
metadata:
  author: larecipe
  version: "3.0"
---

# Update LaRecipe docs before you push

Docs ship in the same push as the code they describe. You changed the code, so you have the context; update the pages now, while it is fresh, not in a follow-up pull request.

## 1. Find the affected pages

```bash
php artisan larecipe:affected --uncommitted --base=main --format=json
```

Use the default branch named in the LaRecipe section of `AGENTS.md` for `--base`. The report lists:

- `pages` with `status: "stale"`: the code they document changed in this branch and the page did not. **These are your job.**
- `pages` with `status: "updated"`: already changed in this branch. Check they match the final code.
- `drift`: pages that fell behind before this branch. Mention them to the user; fix them only if they are in scope.

Each page lists the `matched_sources` and `changed_files` that made it stale.

If the LaRecipe pre-push check held your push, its message already lists the stale pages: start from that list.

## 2. Update each stale page

For each page:

1. Read the page, then read what changed: `git diff <base>...HEAD -- <changed files>` plus your uncommitted edits.
2. Change only what the code change made wrong or incomplete: a renamed option, a new parameter, a removed step, a new behavior. Keep every unrelated sentence exactly as it is. A docs diff that rewrites a page to fix one line is hard to review.
3. Replace pasted values you touch with live references where possible (`<Route name>`, `<Artisan command>`, `<Model name>`, `<Event name>`). See [live references](references/live-references.md).
4. Keep `sources:` accurate: add the new files the page now documents, remove ones it no longer does.
5. Follow [the writing style](references/writing-style.md), easy first: a new option or edge case goes after the common case (or in an `<Accordion>`), never ahead of it. Use only the components in [the catalog](references/components.md). Frontmatter keys are in [frontmatter](references/frontmatter.md).

Never write configuration or environment values into docs. Name a config key in inline code; never print its value.

On a **database set**, never edit files or rows: read with `read_page`, write with the `write_page` MCP tool, which records the change in the branch's docs migration. See [database sets](references/database-sets.md).

## 3. When no docs change is needed

Sometimes the change is internal (a refactor, a test, a performance fix) and the page is still true. Then tell the user in one sentence why the page needs no change. If the pre-push check held the push, push again: the second attempt for the same commit and pages is allowed.

## 4. Gaps the change opened

- New public behavior with no page (a new route, command, model or event): suggest a page, and write it with the `larecipe-write-docs` skill if the user agrees.
- A touched page with no `sources:`: run `php artisan larecipe:sources:suggest --page=<path>` and add the sources that fit.

## 5. Check and ship

1. `php artisan larecipe:validate`, and fix what it reports.
2. If pages were added, removed or retitled and `AGENTS.md` carries a docs index, run `php artisan larecipe:agents-md`.
3. Follow the review mode in `AGENTS.md`:
   - `required`: show the user the docs diff and wait for approval before committing it.
   - `auto`: update the docs, commit and push without asking.
4. Commit the docs (or the docs migration) with the code and push them together.
5. Summarize the docs change in one line for the pull request: which pages, and why.

## Translations

When the set has more than one language, a change to a page in the default language leaves its translations behind. Check them with `--locale`:

```bash
php artisan larecipe:affected --uncommitted --base=main --locale=ar --format=json
```

Follow [the translation workflow](references/translations.md) for each translation it lists.
