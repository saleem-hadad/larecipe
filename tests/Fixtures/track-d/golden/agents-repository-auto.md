<!-- larecipe:start -->
## Documentation (LaRecipe)

This app's docs live in `docs/` and are served by LaRecipe. Docs ship in the same push as the code they describe.

Before you push a branch or open a pull request:

1. Run `php artisan larecipe:affected --uncommitted --base=main` to list the docs pages your change affects.
2. Update each affected page with the `larecipe-update-docs` skill. Keep unrelated text untouched.
3. Run `php artisan larecipe:validate` and fix what it reports.
4. Commit the docs with the code and push them together.

Review mode is `auto`: update the docs and push without asking.

Search and read the docs with the `larecipe` MCP server. Never write configuration or environment values into docs.
<!-- larecipe:end -->
