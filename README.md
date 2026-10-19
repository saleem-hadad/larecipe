<h1 align="center">LaRecipe</h1>

<p align="center"><strong>Docs that keep up with your code.</strong></p>

<p align="center">
LaRecipe lives inside your Laravel app. Your coding agents write the docs and update them with every change.<br>
People get a fast, beautiful site; agents get Markdown, <code>llms.txt</code> and MCP.
</p>

<p align="center">
<a href="https://larecipe.saleem.dev">Read the docs</a> ·
<a href="#upgrading-from-larecipe-2">Upgrade from v2</a> ·
<a href="CHANGELOG.md">Changelog</a>
</p>

---

## Get started

```bash
composer require larecipe/larecipe:^3.0
php artisan larecipe:install
```

Open `/docs`. The installer writes a short, real Introduction from your app (its name, its Laravel version, its README), so the first page is never lorem ipsum. It also sets up the coding agents it finds (Claude Code, Codex, Cursor, Gemini, Copilot): instructions in `AGENTS.md`, four skills, the MCP server and a pre-push check.

Want starter docs for your whole app? Run the installer with `--onboard=product,api` (or `package`, `internal`) and your local coding agent writes them, using the `larecipe-write-docs` skill.

No Node in your app: the UI ships prebuilt.

## Push code. The docs follow.

Docs rot because code changes every day. LaRecipe keeps them current in the same pull request as the code:

- **Pages declare what they describe.** A `sources:` list in the frontmatter ties a page to files, folders or classes.
- **Agents know the rule.** `AGENTS.md` tells every coding session to update the affected pages just before it pushes.
- **A pre-push hook catches the moment it forgets.** When a push changes code that a page describes and the page was not updated, the agent is stopped with the exact pages to fix. Never an agent running in CI.
- **You review, or you choose auto.** Docs changes are commits in the PR (Markdown, or docs migrations for a database source). Owners who trust the loop can switch to `review: auto`.

Need more power? `php artisan larecipe:sync` runs a docs agent built on `laravel/ai`, on your own provider key, on demand.

## For people

- A fast reading experience with a design of its own, light and dark, right to left, phone to desktop. Server-rendered HTML, then React takes over without moving a pixel.
- Search in the browser with fuzzy and prefix matching, no Algolia and no account. A `SearchDriver` contract for custom engines.
- Versions, languages (Arabic and any other), gate blocks that show content per role, private sets behind your app's own auth.
- Built-in components: `Callout`, `Steps`, `Tabs`, `CodeGroup`, `Card`, `Accordion`, `FileTree`, `Frame`, `Video`, `Mermaid`, `Math` and more.
- Live references (`<Route>`, `<Artisan>`, `<Model>`, `<Event>`) read facts from your running app, so they cannot drift. No component ever reads config or environment values.
- Your own React components, built with `@larecipe/vite` and sharing LaRecipe's single copy of React.
- Ask AI for readers, with citations (needs `laravel/ai`).

## For agents

Every page answers agents too:

| Surface | Where |
| --- | --- |
| Markdown twin of every page | add `.md` to any page URL, or send `Accept: text/markdown` |
| Docs index and full text | `/docs/llms.txt`, `/docs/llms-full.txt` |
| An agent skill for your docs | `/docs/skill.md`, `/.well-known/agent-skills/index.json` |
| MCP server | `/docs/mcp` over HTTP, `php artisan larecipe:mcp` over stdio |

`php artisan larecipe:doctor` grades how ready your docs are for agents and tells you what to fix.

## Where the docs live

- **`repository`** (recommended): Markdown in your repo, next to the code, reviewed in pull requests.
- **`database`**: managed in an admin UI, always behind a cache. Changes made in a pull request ship as docs migrations that `php artisan migrate` applies on deploy.

Switch at any time with `php artisan larecipe:source:move`.

## Upgrading from LaRecipe 2

v3 is a new package (`larecipe/larecipe`) with no backward compatibility. Bring your docs over with one command:

```bash
php artisan larecipe:upgrade
```

It converts your v2 Markdown, navigation and Vue components, and the `larecipe-upgrade-v2` skill lets your coding agent finish whatever needs judgment.

## Requirements

PHP 8.3+, Laravel 12.62+ or 13.

## Contributing

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

LaRecipe is open-source software licensed under the [MIT license](LICENSE).
