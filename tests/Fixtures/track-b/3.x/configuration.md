---
title: Configuration
description: Routes, sources, search and access live in config/larecipe.php. Content and presentation live next to your docs.
---

# Configuration

LaRecipe splits configuration in two. Infrastructure (routes, the source driver, caching, AI) lives in `config/larecipe.php`. Content and presentation (name, theme, versions, navigation) live in `larecipe.json` next to your docs.

## Doc sets

One app can serve several doc sets, each with its own route, source and access rules.

```php
'sets' => [
    'docs' => ['route' => ['prefix' => 'docs']],
    'handbook' => ['route' => ['prefix' => 'handbook'], 'access' => ['mode' => 'auth']],
],
```

## Search

The default driver searches in the browser. The index is built per version, locale and audience, so a private page never reaches a guest's browser.

## Access

Set `access.mode` to `public`, `auth` or `gate`. The same policy applies to every surface: HTML, Markdown, search, `llms.txt` and MCP.
