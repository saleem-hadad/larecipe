---
title: Release checklist
description: What to check before you tag a release, section by section.
inserts:
  article.after: false
---

# Release checklist

Work through these sections in order. Each one is short; together they keep a release from surprising your readers.

## Freeze the scope

Agree on what ships. Anything that is not merged by the freeze waits for the next release. Write the list down where the team can see it, and link each item to its pull request.

A scope that keeps moving is the most common reason docs fall behind. Freezing it gives the docs a fixed target.

## Update the docs

Run the affected-pages check against the release branch. It lists every page whose sources changed since the last tag.

### Read the diff

Read each docs diff next to its code diff. The question is always the same: would a reader who only saw the new page do the right thing?

### Check the examples

Examples should come from real code. Snippet includes fail validation when the region they point at disappears, which is exactly what you want.

## Validate

Run the validator with JSON output in CI. Broken links, unknown components and missing references fail the build instead of reaching a reader.

```bash
php artisan larecipe:validate --json
```

## Check provenance

Open a few changed pages and read the provenance line. It should name the change that touched the page and the person who approved it.

## Search and agents

Search for the new feature by name. Then fetch the page as Markdown and read it the way an agent will. If the Markdown is confusing, the HTML probably is too.

### MCP

Call the search tool from your coding agent and make sure the new page is in the top results for the obvious query.

## Tag the release

Tag the release from the merge commit. The docs version that matches the tag is the one readers will see by default.

## Announce it

Write the changelog entry in plain words. Say what changed, why, and what a reader has to do, if anything.

## After the release

Watch the zero-result searches for a week. They are the clearest signal of what the docs still do not cover.
