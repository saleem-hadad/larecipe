# LaRecipe v2 to v3 mapping

| v2 | v3 |
| --- | --- |
| `binarytorch/larecipe`, namespace `BinaryTorch\LaRecipe` | `larecipe/larecipe`, namespace `LaRecipe\` |
| `docs.path` (`resources/docs`) | `sets.docs.source.path` in `config/larecipe.php`; new installs use `docs/` |
| `docs.route`, `domain`, middleware | `sets.{set}.route.prefix`, `.domain`, `.middleware` |
| `versions.published`, `versions.default` | `versions` in `larecipe.json`, one folder per version |
| `docs.landing` | `landing` in `larecipe.json`, or the version's `index.md` |
| Sidebar from each version's `index.md` | `navigation.json` per version, or zero-config navigation from folders and frontmatter `order` |
| `settings.auth`, `guard` | `sets.{set}.access.mode` (`public`, `auth`, `gate`) |
| `viewLarecipe` gate | Same name; it now receives the `Page` |
| Alerts `> {warning} text` | GitHub alerts `> [!WARNING]` or `<Callout type="warning">` |
| `<larecipe-card>`, `<larecipe-badge>`, `<larecipe-button>` | `<Card>`, `<Badge>`, `<Button>` |
| `<larecipe-progress>` | Removed |
| `<a name="x"></a>` before a heading | `{#x}` heading attribute |
| `{{route}}` in links | Relative `.md` links |
| `{{version}}` | Still supported; relative links preferred |
| Blade in Markdown | Components, snippet includes, plain text; `trusted_blade` exists only as a temporary migration aid for repository sets |
| Custom Vue components | React components built with `@larecipe/vite`, plus a `LaRecipe::component()` PHP definition |
| `LaRecipe::script()`, `LaRecipe::style()` | `assets.css` in `larecipe.json`, React extensions |
| Themes, `colors.primary`, `code_theme` | `theme` tokens in `larecipe.json`, `theme.css`; code themes follow light and dark mode |
| `fav` | `favicon` in `larecipe.json` |
| `show_side_bar` | Frontmatter `layout` (`default`, `wide`, `full`) |
| Font Awesome icons | Lucide icon names |
| Algolia DocSearch | Built-in search (custom search drivers for advanced cases) |
| Disqus | The page feedback widget, edit and report links |
| `ga_id` | `integrations.analytics` in `larecipe.json` (GA4, Plausible, Fathom, PostHog, custom script) |
| SEO meta in config | Frontmatter `description` per page, plus canonical, JSON-LD and sitemap |
| `larecipe:docs` | `larecipe:make:version`, `larecipe:make:page` |
| `larecipe:theme`, `larecipe:asset` | Extensions built with `@larecipe/vite` |
| Dark theme, RTL and feedback packages | Built in |
