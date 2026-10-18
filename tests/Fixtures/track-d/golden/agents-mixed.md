<!-- larecipe:start -->
## Documentation (LaRecipe)

This app's docs are served by LaRecipe from 2 doc sets: `docs` in `docs/` and `handbook` in the database. Docs ship in the same push as the code they describe.

Before you push a branch or open a pull request:

1. Run `php artisan larecipe:affected --uncommitted --base=main` to list the docs pages your change affects.
2. Update each affected page with the `larecipe-update-docs` skill. Keep unrelated text untouched. Pages of `handbook` live in the database: edit them through the LaRecipe MCP tools; they write a docs migration in `database/migrations/docs/`; commit it. Never edit docs rows or write SQL by hand.
3. Run `php artisan larecipe:validate` and fix what it reports.
4. Commit the docs and the docs migration with the code and push them together.

Review mode is `required`: show the user the docs diff and wait for approval before committing it.

Write docs for people first: open each page with the common case in a few short lines, then put options, edge cases and internals after it (in an `<Accordion>` or on a linked page). Order pages from easy to advanced. Agents still read everything in the Markdown twin.

Search and read the docs with the `larecipe` MCP server. Never write configuration or environment values into docs.
<!-- larecipe:end -->
