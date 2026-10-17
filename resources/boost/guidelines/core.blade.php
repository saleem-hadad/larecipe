{{-- LaRecipe guidelines for AI coding agents, shipped through Laravel Boost. --}}
{{-- The instructions are generated from this app's config, the same text LaRecipe writes into AGENTS.md without Boost. --}}
{!! \LaRecipe\Domains\Sync\Laravel\BoostGuideline::instructions() !!}

For docs work, use the LaRecipe skills: `larecipe-write-docs` to write new pages (and run onboarding), `larecipe-update-docs` to update the pages a change made stale, `larecipe-audit-docs` to review the whole set, and `larecipe-upgrade-v2` to finish a LaRecipe 2.x upgrade.

@scoped(['docs/**'])
- Every docs page has `title`, `description` (one sentence) and `sources:` frontmatter; `sources:` lists the code the page documents, so `php artisan larecipe:affected` can tell when it goes stale.
- Write route, command, model and event facts with live references (`<Route name="...">`, `<Artisan command="...">`, `<Model name="...">`, `<Event name="...">`) instead of pasting values.
- Use only the components in the skills' component catalog, and link pages with relative `.md` paths.
- Never write configuration or environment values into docs. Name a config key in inline code; never print its value.
- Run `php artisan larecipe:validate` after editing docs and fix what it reports.
@endscoped

@scoped(['database/migrations/docs/**'])
- Docs migrations are written by the LaRecipe MCP author tools (`write_page`, `move_page`, `update_navigation`). Never hand-write or hand-edit one, and never write SQL for docs.
- The branch's docs migration is part of the change: commit it with the code and push them together.
@endscoped
