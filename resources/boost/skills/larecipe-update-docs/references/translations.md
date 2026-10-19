# Translation workflow

A doc set with several languages keeps the default language at the version root and each other language in a folder named after its locale code (`docs/ar/billing.md` translates `docs/billing.md`). A translation falls behind when its source page changes and it does not.

## Find translations that fell behind

```bash
php artisan larecipe:affected --uncommitted --base=main --locale=ar --format=json
```

- `pages` with `reason: "translation"`: the source page changed in this branch and the translation did not.
- `drift` with `reason: "translation"`: the source page changed in earlier commits.

## Update a translation

1. Read the source page's diff (`git diff <base>...HEAD -- docs/billing.md`) and the current translation.
2. Translate only the changed parts and fit them into the existing translation. Keep the translator's earlier wording everywhere else.
3. Keep code, commands, file paths, config key names, component tags and their props exactly as in the source page. Translate prose, headings, `title` and `description`.
4. Keep the same structure: the same headings in the same order, so anchors and the table of contents line up across languages.
5. Use the terms the existing translation already uses. Consistency matters more than your own preference.
6. Right-to-left languages need nothing special in Markdown: the locale's direction is set in `larecipe.json`.

If you cannot translate well into the language, say so to the user instead of guessing, and list the translations that need a human.

On a database set, write translations with the `write_page` MCP tool and its `locale` argument, like any other page.
