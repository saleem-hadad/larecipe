<!-- larecipe:start -->
## Documentation (LaRecipe)

This app's docs live in the database (doc set `handbook`) and are served by LaRecipe. Docs ship in the same push as the code they describe, as docs migrations in `database/migrations/docs/`.

Before you push a branch or open a pull request:

1. Run `php artisan larecipe:affected --uncommitted --base=main` to list the docs pages your change affects.
2. Update each affected page with the `larecipe-update-docs` skill, through the LaRecipe MCP tools; they write a docs migration in `database/migrations/docs/`; commit it. Never edit docs rows or write SQL by hand. Keep unrelated text untouched.
3. Run `php artisan larecipe:validate` and fix what it reports.
4. Commit the docs migration with the code and push them together.

Review mode is `required`: show the user the docs diff and wait for approval before committing it.

Search and read the docs with the `larecipe` MCP server. Never write configuration or environment values into docs.
<!-- larecipe:end -->
