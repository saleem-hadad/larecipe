---
title: Frontmatter
description: Page-level settings at the top of a Markdown file.
---

# Frontmatter

Frontmatter holds settings for one page. Everything about the doc set lives in `larecipe.json`.

## Keys

| Key | Type | What it does |
| --- | --- | --- |
| `title` | string | The page title and H1 |
| `description` | string | The lead under the title, and the meta description |
| `order` | integer | Position in zero-config navigation |
| `inserts` | map or `false` | Opt the page out of insert points |
| `sources` | list | Code globs this page documents, for drift detection |
