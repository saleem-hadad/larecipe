---
name: larecipe-upgrade-v2
description: Finish a LaRecipe v2 to v3 migration after php artisan larecipe:upgrade. Use when upgrading from LaRecipe 2.x (binarytorch/larecipe), when larecipe-upgrade-report.md exists, or when docs contain v2 leftovers such as Vue components, Blade in Markdown, {{route}} links or Font Awesome alerts.
license: MIT
compatibility: A Laravel app with larecipe/larecipe 3.x installed, so php artisan larecipe:* works.
metadata:
  author: larecipe
  version: "3.0"
---

# Finish the v2 upgrade

`php artisan larecipe:upgrade` does everything that can be converted mechanically: config, navigation from `index.md`, alerts, `larecipe-*` tags, anchors and links. It writes a report of what is left. Your job is the rest, page by page, until `php artisan larecipe:validate` passes.

## 1. Start from the report

```bash
php artisan larecipe:upgrade           # dry run: the plan
php artisan larecipe:upgrade --apply   # writes the changes and the report
```

Read `larecipe-upgrade-report.md` and its JSON twin. Work through the items flagged as manual, in this order: anything that breaks rendering, then content, then looks.

## 2. Common manual items

See [the v2 to v3 mapping](references/v2-mapping.md) for every v2 feature and its replacement.

- **Blade in Markdown** (`@if`, `{{ $var }}`, `@include`): v3 does not evaluate Blade in content. Replace logic with components, snippet includes (`<<< @/path#region`) or plain text. Replace values that came from config with prose that names the key; never paste the value.
- **Custom Vue components:** rebuild them as React components in the host app with `@larecipe/vite` and `defineExtension()`, and register a PHP definition with `LaRecipe::component('Name')` (props, a server fallback that works without JavaScript, and a Markdown form for agents). Until then, replace the tag with plain Markdown so the page still reads well.
- **Themes and custom CSS:** move colors, fonts and radius to the `theme` tokens in `larecipe.json`; keep the rest in a `theme.css` listed under `assets.css`. Font Awesome icons become Lucide icon names.
- **`{{route}}` links:** relative `.md` links (`./installation.md#env`). `{{version}}` still works.
- **Removed features:** Algolia (search is built in), Disqus (use the feedback widget), `<larecipe-progress>` (no replacement), Google Analytics `ga_id` (moves to `integrations.analytics` in `larecipe.json`).

Use only components in [the catalog](references/components.md) and keys in [frontmatter](references/frontmatter.md), [the manifest](references/manifest.md) and [navigation](references/navigation.md).

## 3. Make every page earn its keep

While you are in each page:

- Give it a `description` and, where it documents code, `sources:` (`php artisan larecipe:sources:suggest` helps).
- Replace pasted route URIs, command signatures and column lists with live references. See [live references](references/live-references.md).
- Never copy configuration or environment values into docs, even if the v2 page had them.

Follow [the writing style](references/writing-style.md), but keep the upgrade a migration: do not rewrite pages that only need converting.

## 4. Finish

1. `php artisan larecipe:validate` until it reports no errors.
2. `php artisan larecipe:agents` to write the agent instructions and skills for the upgraded app.
3. Follow the review mode in `AGENTS.md`, then commit the upgrade as its own change with a summary of the manual items you handled.
