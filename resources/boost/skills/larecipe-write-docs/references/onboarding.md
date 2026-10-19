# Onboarding: real starter docs from the app

Onboarding writes a team's first real docs from the app itself, for the audiences they choose. It usually starts from `php artisan larecipe:onboard`, which runs you headless with a prompt like:

> Use the larecipe-write-docs skill to run onboarding for audiences: api, internal (set: docs)

Audiences can be combined. Run each preset below for each audience named, in the doc set named (default: the default set).

## The workflow

1. **Read the room.** Read `AGENTS.md`, `README.md`, `composer.json` and the existing docs. The installer already wrote an Introduction page from facts (app name, Laravel version, packages, README opening): keep it and improve it, never replace it with something vaguer.
2. **Plan before writing.** For each audience, list the pages you will write (the outline below), each with the code it will read. Skip a page when the app has nothing for it (no API routes means no API pages). Never invent features to fill an outline.
3. **Write each page** with the main skill: frontmatter with `title`, `description` and `sources`, answer first, real examples, live references instead of pasted values.
4. **Navigation.** Group the pages by audience. With several audiences in one set, give each its own group or tab in `navigation.json`.
5. **Validate.** Run `php artisan larecipe:validate` and fix every error.
6. **Leave it for review.** Do not commit. The pages stay in the working tree (a repository set) or in the branch's docs migration (a database set, through the MCP tools), so the user reviews them first.
7. **Report.** Finish with the list of pages you created, one line each, and anything you left out and why.

Write only inside the docs folder (or, for a database set, only through the LaRecipe MCP tools). Read anything in the repository; change nothing else.

## What never goes into docs, for every audience

- Secrets and settings: `.env` values, keys, tokens, passwords, connection strings, hostnames, internal URLs. Name a config key in inline code when readers need it; never its value.
- Customer data: names, emails, IDs, records from seeders or fixtures that look real.
- Guesses: if the code does not say it, the page does not say it. Ask, or leave a short note in your report.

## Product users

For people who use the product, not its code.

- **Outline:** what the product does; getting started; one page per main feature; FAQ.
- **Read:** routes and controllers behind user-facing features, Blade or Inertia pages, form validation messages, existing help text, the README.
- **Tone:** second person, task first ("To export invoices, open Billing, then Exports"). No code internals, no class names, no artisan commands.
- **Never:** internal routes, admin-only features (unless the set is private), implementation details.
- **Suggested set:** `docs`, public.

## API consumers

For developers who call the app's HTTP API.

- **Outline:** authentication; conventions (pagination, errors, rate limits, versioning); one page per resource with a `<Route name="..." />` reference for each endpoint; webhooks; changelog or versioning policy.
- **Read:** `routes/api.php` and route files it loads, controllers, FormRequests (their rules are the request contract), API Resources (the response shape), auth guards and Sanctum or Passport setup, rate limiters, Scramble or Scribe output when present.
- **Tone:** precise and scannable. Request and response examples taken from Resources and tests.
- **Never:** real tokens or keys in examples (use a clearly fake placeholder such as `YOUR_API_TOKEN`), internal-only endpoints.
- **Suggested set:** `docs` or a separate `api` set, public.

## Package users

For developers who install this code as a Composer package.

- **Outline:** installation; configuration (key names in prose, with what each controls); usage by feature; upgrade guide; public API by class.
- **Read:** `composer.json`, the service provider, the published config file's structure (keys and comments, never values from an app's `.env`), public classes and their docblocks, tests as examples.
- **Tone:** practical; every page should be copy-paste friendly.
- **Never:** internal classes marked `@internal`, values from a local `.env`.
- **Suggested set:** `docs`, public, versioned (one folder per major version).

## Internal team

For the people who build and run this app.

- **Outline:** architecture overview; domain glossary; local setup; conventions; data model with `<Model name="..." />` references; queues, jobs and schedules; deployment; runbooks for common incidents.
- **Read:** the whole codebase: `app/` (or `app/Domains/`), migrations, `routes/`, `config/` structure, CI configuration, `AGENTS.md`, existing notes.
- **Tone:** direct and dense; assume a Laravel developer on day one.
- **Never:** secrets, production hostnames, credentials, personal data, even though the set is private.
- **Suggested set:** `handbook`, with `access.mode` set to `auth` or `gate` in `config/larecipe.php`. If the set does not exist yet, say so in your report instead of writing internal pages into a public set.
