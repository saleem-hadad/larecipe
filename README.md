<p align="center">
<a href="https://larecipe.saleem.dev"><img src=".github/assets/screenshot.webp" alt="The LaRecipe docs site in a browser: the sidebar, the Introduction page, and the panel of actions for agents, such as Copy as Markdown and Connect MCP."></a>
</p>

<h1 align="center">Docs that keep up with your code</h1>

<p align="center">
LaRecipe is a docs site that lives inside your Laravel app.<br>
Your coding agent writes the pages and keeps them current,<br>
in the same pull request as the code.
</p>

<p align="center">
<a href="https://github.com/saleem-hadad/larecipe/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/saleem-hadad/larecipe/tests.yml?branch=main&label=tests" alt="Tests"></a>
<a href="https://github.com/saleem-hadad/larecipe/releases"><img src="https://img.shields.io/github/v/release/saleem-hadad/larecipe?label=release" alt="Latest release"></a>
<a href="https://packagist.org/packages/binarytorch/larecipe/stats"><img src="https://img.shields.io/packagist/dt/binarytorch/larecipe?label=downloads" alt="Downloads"></a>
<a href="https://github.com/saleem-hadad/larecipe/stargazers"><img src="https://img.shields.io/github/stars/saleem-hadad/larecipe?style=flat&label=stars" alt="GitHub stars"></a>
<a href="LICENSE"><img src="https://img.shields.io/github/license/saleem-hadad/larecipe" alt="MIT license"></a>
</p>

<p align="center">
<a href="https://larecipe.saleem.dev"><strong>Read the docs</strong></a> ·
<a href="#-get-started">Get started</a> ·
<a href="#-upgrading-from-larecipe-2">Upgrade from v2</a> ·
<a href="CHANGELOG.md">Changelog</a>
</p>

---

## 🚀 Get started

Two commands, inside your Laravel app:

```bash
composer require larecipe/larecipe:^3.0
php artisan larecipe:install
```

Now open `/docs`. Your docs site is already there, and its first page is written from your app. No lorem ipsum.

The installer also gets your coding agents ready. It finds Claude Code, Codex, Cursor, Gemini and Copilot, and sets up:

- instructions in `AGENTS.md`
- four LaRecipe skills
- the docs MCP server
- a pre-push hook

There is no Node and no build step. The UI ships prebuilt.

Want starter docs for your whole app? Add `--onboard=product,api` to the install command, and your coding agent writes them.

<details>
<summary><strong>Or let your coding agent do the whole install</strong></summary>

<br>

Paste this prompt into Claude Code, Codex, Cursor or any coding agent, inside your Laravel app:

```text
Install LaRecipe in this Laravel app:

1. Run `composer require larecipe/larecipe:^3.0`.
2. Run `php artisan larecipe:install --no-interaction` to keep the defaults.
3. Run `php artisan larecipe:doctor` and fix anything it reports.
4. Read the app and write starter docs with the larecipe-write-docs skill.

Then tell me the URL of the docs site.
```

</details>

Next: the [installation guide](https://larecipe.saleem.dev/3.x/get-started/installation).

## 🔄 How the docs keep up

LaRecipe's first README, back in 2018, opened with *"Writing documentation is boring? Hah, let's see."* Today your agent writes the docs. The hard part is keeping them true while the code changes every day. So LaRecipe keeps them in step:

1. **Each page lists the code it describes.** Files, folders or classes, under `sources:` in its frontmatter.
2. **Your agent updates the pages a change affects.** `AGENTS.md` tells it to, just before every push.
3. **A hook catches what it forgets.** It holds the push once and names the pages to update. The next push always goes through.
4. **You review docs and code together.** Both land in the same pull request. Or switch the review mode to `auto`.

This is what your agent sees when it pushes a billing change and forgets the billing page:

```text
LaRecipe: this push changes code that 1 docs page describes, and it was not updated:
  - docs/billing.md   (sources: app/Billing/**)
Update it with the larecipe-update-docs skill, run php artisan larecipe:validate,
show the user the docs diff and wait for approval, then commit and push again.
If no docs change is needed, tell the user why and push again; the next push is allowed.
```

No AI agent runs in your CI. When you want extra help, `php artisan larecipe:sync` runs a docs agent in your app, on your own AI provider key.

Next: [how it works](https://larecipe.saleem.dev/3.x/keeping-current/how-it-works).

## ✨ Features

### For readers

- **Fast and polished.** Light and dark, on phone and desktop.
- **Search built in.** It runs in the browser, with no service and no account.
- **Versions and languages.** Right to left included, with a fallback for pages not translated yet.
- **Private docs.** Your app's auth and gates decide who reads, down to one paragraph.
- **Components.** Callouts, steps, tabs, cards, diagrams, math and your own React components.
- **Live references.** Routes, commands, models and events read from your running app, so they never go stale.
- **Ask AI.** Answers written only from your docs, with the pages they cite.
- **Static export.** Publish your public docs to any static host.

See it live: [larecipe.saleem.dev](https://larecipe.saleem.dev) is built with LaRecipe.

### For agents

Agents read the same pages as people, in the formats they prefer:

| What | Where |
| --- | --- |
| Any page as Markdown | add `.md` to its URL, or send `Accept: text/markdown` |
| Docs index and full text | `/docs/llms.txt` and `/docs/llms-full.txt` |
| A skill for your docs | `/docs/skill.md` |
| MCP server | `/docs/mcp` over HTTP, or `php artisan larecipe:mcp` locally |

Run `php artisan larecipe:doctor` to see how ready your docs are for agents and what to fix.

## 📚 Where the docs live

- **In your repository** (recommended). Markdown next to the code, reviewed in pull requests.
- **In your database.** Edited in an admin UI and always cached. Changes from a pull request ship as docs migrations that run on deploy.

You can switch at any time with `php artisan larecipe:source:move`.

## ⬆️ Upgrading from LaRecipe 2

LaRecipe 2 was downloaded more than 3 million times. Thank you for every one of them.

LaRecipe 3 is a new package, rebuilt from scratch, with no backward compatibility. The upgrade is a command:

```bash
composer require larecipe/larecipe:^3.0
php artisan larecipe:upgrade
```

1. **It starts as a dry run.** It writes `larecipe-upgrade-report.md` and changes nothing.
2. **Add `--apply` to convert.** Your Markdown, navigation and LaRecipe's v2 components move to v3.
3. **Your agent finishes the rest.** With the `larecipe-upgrade-v2` skill, it handles what the report lists, such as custom Vue components and Blade.

Next: the [upgrade guide](https://larecipe.saleem.dev/3.x/upgrade).

## 📋 Requirements

- PHP 8.3 or later
- Laravel 12.62 or later, or Laravel 13
- `laravel/ai`, only for Ask AI and `larecipe:sync`

## 🙌 Contributors

This project exists thanks to all the people who contribute. [Contribute](CONTRIBUTING.md).

<a href="https://github.com/saleem-hadad/larecipe/graphs/contributors"><img src="https://opencollective.com/larecipe/contributors.svg?width=890&button=false" alt="The people who contribute to LaRecipe"></a>

### Work on LaRecipe

```bash
composer install
pnpm install
pnpm build              # the prebuilt UI in dist/
vendor/bin/pest         # PHP tests
pnpm test               # UI tests
pnpm test:browser       # browser tests against the workbench
```

Read `ARCHITECTURE.md` for how LaRecipe is built, `DESIGN.md` for how it looks and `PRODUCT.md` for what it is for.

## 📄 License

LaRecipe is open-source software by [Saleem Hadad](https://github.com/saleem-hadad), licensed under the [MIT license](LICENSE).

⭐ If LaRecipe saves you time, star the repo. It helps other Laravel developers find it.
