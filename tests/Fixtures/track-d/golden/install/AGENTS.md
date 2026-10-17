<!-- larecipe:start -->
## Documentation (LaRecipe)

This app's docs live in `docs/` and are served by LaRecipe. Docs ship in the same push as the code they describe.

Before you push a branch or open a pull request:

1. Run `php artisan larecipe:affected --uncommitted --base=main` to list the docs pages your change affects.
2. Update each affected page with the `larecipe-update-docs` skill. Keep unrelated text untouched.
3. Run `php artisan larecipe:validate` and fix what it reports.
4. Commit the docs with the code and push them together.

Review mode is `required`: show the user the docs diff and wait for approval before committing it.

Search and read the docs with the `larecipe` MCP server. Never write configuration or environment values into docs.

### Docs index: Acme Invoices

1 page. One line per page: path, title, description. Read a page with the `read_page` tool of the `larecipe` MCP server, or open its file in `docs/`. Refresh this index with `php artisan larecipe:agents-md`.

- `index`: Introduction. Acme Invoices sends invoices for small teams and chases the ones that are late.
<!-- larecipe:end -->
