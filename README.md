<p align="center">
<a href="https://larecipe.saleem.dev"><img src=".github/assets/screenshot.webp" alt="The LaRecipe docs site in a browser: the sidebar, the Introduction page, and the panel of actions for agents, such as Copy as Markdown and Connect MCP."></a>
</p>

<h1 align="center">Docs that keep up with your code</h1>

<p align="center">
LaRecipe is a docs site that lives inside your Laravel app.<br>
Your coding agent writes the pages and updates them in the same pull request as the code,<br>
so people and agents always read the current answer.
</p>

<p align="center">
<a href="https://github.com/saleem-hadad/larecipe/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/saleem-hadad/larecipe/tests.yml?branch=main&label=tests" alt="Tests"></a>
<a href="https://github.com/saleem-hadad/larecipe/releases"><img src="https://img.shields.io/github/v/release/saleem-hadad/larecipe?label=release" alt="Latest release"></a>
<a href="LICENSE"><img src="https://img.shields.io/github/license/saleem-hadad/larecipe" alt="MIT license"></a>
</p>

<p align="center">
<a href="https://larecipe.saleem.dev"><strong>Read the docs</strong></a> ·
<a href="#get-started">Get started</a> ·
<a href="#upgrading-from-larecipe-2">Upgrade from v2</a> ·
<a href="CHANGELOG.md">Changelog</a>
</p>

---

## Get started

```bash
composer require larecipe/larecipe:^3.0
php artisan larecipe:install
```

Open `/docs`. The first page is a short, real Introduction written from your app (its name, its Laravel version, its README), never lorem ipsum.

The installer also sets up the coding agents it finds (Claude Code, Codex, Cursor, Gemini and Copilot): instructions in `AGENTS.md`, four skills, the MCP server and the pre-push hook. There is no Node and no build step in your app: the UI ships prebuilt.

Want starter docs for your whole app? Add `--onboard=product,api` (or `package`, `internal`) and your coding agent writes them with the `larecipe-write-docs` skill.

More in the [installation guide](https://larecipe.saleem.dev/3.x/get-started/installation).

## How the docs keep up

Docs rot because code changes every day. LaRecipe moves the docs into the pull request:

1. **Each page lists the code it describes.** A `sources:` list in its frontmatter ties the page to files, folders or classes.
2. **Your coding agent updates the pages a change affects**, just before it pushes. `AGENTS.md` tells every session to.
3. **A hook catches the push it forgets.** It holds the push once and names the pages to update. The second push always goes through, so the agent is never stuck.
4. **You review docs and code together**, in the same pull request. Trust the loop? Switch the review mode to `auto`.

No agent ever runs in your CI. When you want more power, `php artisan larecipe:sync` runs a docs agent inside your app, on your own AI provider key, on demand.

More in [how it works](https://larecipe.saleem.dev/3.x/keeping-current/how-it-works).

## What readers get

See it live: [larecipe.saleem.dev](https://larecipe.saleem.dev) is built with LaRecipe.

- **A fast site with a design of its own.** Light and dark, right to left, phone to desktop. Server-rendered HTML, then React takes over without moving a pixel.
- **Search with nothing to run.** Fuzzy and prefix matching in the browser, with no search service and no account. A `SearchDriver` contract for custom engines.
- **Versions, languages and private docs.** Your app's own auth and gates decide who reads, down to one paragraph.
- **Components in Markdown.** `Callout`, `Steps`, `Tabs`, `CodeGroup`, `Card`, `Accordion`, `FileTree`, `Mermaid`, `Math` and more, plus your own React components built with `@larecipe/vite`.
- **Live references.** `<Route>`, `<Artisan>`, `<Model>` and `<Event>` read facts from your running app, so they cannot go stale.
- **Ask AI.** Answers written only from your docs, with the pages they cite.
- **Static export.** `php artisan larecipe:export` writes your public docs as plain files for any static host.

## What agents get

Every page answers agents too:

| Surface | Where |
| --- | --- |
| Markdown twin of every page | add `.md` to any page URL, or send `Accept: text/markdown` |
| Docs index and full text | `/docs/llms.txt`, `/docs/llms-full.txt` |
| A skill for your docs | `/docs/skill.md`, `/.well-known/agent-skills/index.json` |
| MCP server | `/docs/mcp` over HTTP, `php artisan larecipe:mcp` over stdio |

`php artisan larecipe:doctor` grades how ready your docs are for agents and tells you what to fix.

## Where the docs live

- **`repository`** (recommended): Markdown in your repo, next to the code, reviewed in pull requests.
- **`database`**: managed in an admin UI, always behind a cache. Changes made in a pull request ship as docs migrations that `php artisan migrate` applies on deploy.

Switch at any time with `php artisan larecipe:source:move`.

## Upgrading from LaRecipe 2

v3 is a new package (`larecipe/larecipe`), rebuilt from scratch with no backward compatibility. Bring your docs over with one command:

```bash
composer require larecipe/larecipe:^3.0
php artisan larecipe:upgrade
```

It converts your v2 Markdown, navigation and Vue components. It starts as a dry run and writes `larecipe-upgrade-report.md`, so nothing changes until you add `--apply`. Your coding agent finishes the items that need judgment with the `larecipe-upgrade-v2` skill.

The full guide: [Upgrade from v2](https://larecipe.saleem.dev/3.x/upgrade).

## Requirements

PHP 8.3+ and Laravel 12.62+ or 13. Ask AI and `larecipe:sync` also need `laravel/ai`.

## Contributing

Bug reports and pull requests are welcome. To work on LaRecipe itself:

```bash
composer install
pnpm install
pnpm build              # the prebuilt UI in dist/
vendor/bin/pest         # PHP tests
pnpm test               # UI tests
pnpm test:browser       # browser tests against the workbench
```

`ARCHITECTURE.md` explains how LaRecipe is built, `DESIGN.md` how it looks, and `PRODUCT.md` what it is for. See [CONTRIBUTING.md](CONTRIBUTING.md) for the rest.

## License

LaRecipe is open-source software by [Saleem Hadad](https://github.com/saleem-hadad), licensed under the [MIT license](LICENSE).
