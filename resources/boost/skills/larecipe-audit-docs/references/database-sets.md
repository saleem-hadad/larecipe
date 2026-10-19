# Database doc sets and docs migrations

A database set keeps its pages in the app's database (teams use it for the admin UI). Its content is not in the repository, so a pull request cannot carry a docs change as Markdown files. Docs migrations solve that: the change travels in the pull request as a migration file and is applied by `php artisan migrate` on deploy.

## How you edit a database set

Always through the LaRecipe MCP author tools on the local `larecipe` server:

| Tool | Does |
| --- | --- |
| `write_page` | Create or update a page: `path`, `frontmatter`, `body`, a one-line `summary` |
| `move_page` | Move or rename a page |
| `delete_page` | Delete a page |
| `update_navigation` | Change the navigation tree (a full tree or a patch) |
| `update_manifest` | Change `larecipe.json` settings (a JSON merge patch) |

Each call does two things: it applies the change to your local database, so the docs can be previewed at once, and it writes the change into the branch's docs migration in `database/migrations/docs/` (one file per branch, rewritten as you go). Read pages first with `read_page` and `search_docs`.

## Rules

- Never write SQL, never edit docs rows directly, never hand-write or hand-edit a docs migration. The tools record the content hash each change was based on, which is what keeps a migration from overwriting an edit someone made in production.
- Pass `based_on` with every change. `read_page` ends with the page's `content_hash`: pass it to `write_page`, `move_page` or `delete_page`, and the change is refused if someone edited the page since you read it. For `update_navigation` and `update_manifest`, pass the `revision.content_hash` your last call returned, or leave `based_on` out. Leave it out for a new page.
- The docs migration is part of the change: commit it with the code and push them together. The pre-push check looks for it.
- Run `php artisan larecipe:validate` after writing. For database sets it also reports unresolved migration conflicts.
- If your local docs look older than production, ask the user to run `php artisan larecipe:pull --set=<set>` before you start, so the migration is based on what is live.

## Review

A change made in a pull request is reviewed in the pull request; the migration applies as published revisions on deploy. The review mode in `AGENTS.md` still decides how you behave in the session: in `required`, show the user the change and wait for approval before committing; in `auto`, commit and push without asking.
