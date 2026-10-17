---
description: Write real starter docs for this Laravel app with LaRecipe, for the audiences you choose (product, api, package, internal).
argument-hint: "[audiences: product api package internal] [set]"
---

Use the larecipe-write-docs skill to run onboarding for audiences: $ARGUMENTS

If no audiences were given, ask which of these the docs are for, and allow several:

- product: people who use the product
- api: developers who call its HTTP API
- package: developers who install it as a Composer package
- internal: the team that builds and runs it

Follow the skill's onboarding workflow (references/onboarding.md). Write only inside the docs folder, or through the LaRecipe MCP tools for a database set. Run `php artisan larecipe:validate` when done, leave the pages uncommitted for review, and finish with the list of pages you created.
