<?php

namespace LaRecipe\Domains\Upgrade\Services;

use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\DTOs\V2Project;

/**
 * Maps a v2 config/larecipe.php to the two v3 files that replace it: the
 * infrastructure config (config/larecipe.php: route, source, access) and the
 * set manifest (larecipe.json: name, versions, landing, theme, analytics).
 *
 * Values are never copied into the report: a v2 config may hold keys (Algolia,
 * analytics) that do not belong in a file people paste into issues.
 */
final class ConfigMapper
{
    /**
     * @param  bool  $gated  the app defines a viewLarecipe gate, which v2 applied to every page
     * @return array{config: string, manifest: array<string, mixed>, entries: list<ReportEntry>}
     */
    public function map(V2Project $project, bool $gated = false): array
    {
        $config = $project->config;
        $entries = [];
        $settings = (array) ($config['settings'] ?? []);
        $ui = (array) ($config['ui'] ?? []);

        $middleware = array_values(array_unique(array_merge(
            array_map('strval', (array) ($config['docs']['middleware'] ?? ['web'])),
            array_map('strval', (array) ($settings['middleware'] ?? [])),
        ))) ?: ['web'];

        $mode = match (true) {
            (bool) ($settings['auth'] ?? false) => 'auth',
            $gated => 'gate',
            default => 'public',
        };

        $entries[] = ReportEntry::changed('config', "The docs keep their route prefix (/{$project->routePrefix()}) and middleware (".implode(', ', $middleware).').', 'config/larecipe.php');
        $entries[] = ReportEntry::changed('config', "Access mode is \"{$mode}\".".match ($mode) {
            'auth' => ' v2 settings.auth was true, so every page needs a signed-in reader.',
            'gate' => ' The app defines the viewLarecipe gate, which v2 checked on every page; v3 does the same in gate mode.',
            default => '',
        }, 'config/larecipe.php');

        if (! empty($settings['guard'])) {
            $entries[] = ReportEntry::manual('config', 'v2 settings.guard picked a guard for the docs. v3 uses the default guard.', 'config/larecipe.php', null, 'Add the guard\'s auth middleware to sets.docs.route.middleware, or make it the default guard.');
        }

        $manifest = array_filter([
            '$schema' => 'https://larecipe.saleem.dev/schemas/3.x/larecipe.json',
            'name' => $project->appName,
            'description' => isset($config['seo']['description']) ? (string) $config['seo']['description'] : null,
            'favicon' => isset($ui['fav']) && $ui['fav'] !== '' ? (string) $ui['fav'] : null,
            'theme' => isset($ui['colors']['primary']) ? ['accent' => (string) $ui['colors']['primary']] : null,
            'versions' => array_map(
                fn (string $version) => array_filter(['id' => $version, 'default' => $version === $project->defaultVersion() ? true : null]),
                $project->versions(),
            ) ?: null,
            'landing' => isset($config['docs']['landing']) ? (string) $config['docs']['landing'] : null,
            'integrations' => ! empty($settings['ga_id']) ? ['analytics' => ['ga4' => ['measurementId' => (string) $settings['ga_id']]]] : null,
        ], fn ($value) => $value !== null);

        $entries[] = ReportEntry::changed('manifest', 'Versions ('.implode(', ', $project->versions()).'), the landing page, the favicon, the primary color (as theme.accent) and the description moved to larecipe.json.', $project->targetDocsPath().'/larecipe.json');

        if (! empty($settings['ga_id'])) {
            $entries[] = ReportEntry::changed('manifest', 'Google Analytics (settings.ga_id) is now a GA4 integration in larecipe.json.', $project->targetDocsPath().'/larecipe.json');
        }

        foreach ($this->dropped($config) as [$key, $message, $fix]) {
            $entries[] = ReportEntry::dropped('config', "{$key}: {$message}", 'config/larecipe.php', null, $fix);
        }

        return ['config' => $this->php($project, $middleware, $mode), 'manifest' => $manifest, 'entries' => $entries];
    }

    /** @return list<array{0: string, 1: string, 2: ?string}> */
    private function dropped(array $config): array
    {
        $dropped = [];
        $ui = (array) ($config['ui'] ?? []);
        $search = (array) ($config['search'] ?? []);

        if (isset($config['cache'])) {
            $dropped[] = ['cache', 'rendered pages are always cached by content hash in v3.', 'Run php artisan larecipe:optimize on deploy (it runs with php artisan optimize).'];
        }

        if (($search['default'] ?? null) === 'algolia' || isset($search['engines']['algolia'])) {
            $dropped[] = ['search.engines.algolia', 'Algolia DocSearch is gone. Search is built in and works offline.', 'Remove the Algolia keys from your .env.'];
        } elseif (isset($config['search'])) {
            $dropped[] = ['search', 'search is built in and always on.', null];
        }

        if (isset($ui['code_theme'])) {
            $dropped[] = ['ui.code_theme', 'code blocks follow the page theme, light and dark.', null];
        }

        if (isset($ui['colors']['secondary'])) {
            $dropped[] = ['ui.colors.secondary', 'v3 themes have one accent color.', 'Fine-tune colors in a theme.css listed under assets.css in larecipe.json.'];
        }

        if (isset($ui['fa_v4_shims'])) {
            $dropped[] = ['ui.fa_v4_shims', 'Font Awesome is gone; v3 uses one icon set (Lucide).', null];
        }

        if (array_key_exists('theme_order', $ui)) {
            $dropped[] = ['ui.theme_order', 'there are no stacked theme packages in v3.', null];
        }

        if (array_key_exists('show_side_bar', $ui)) {
            $dropped[] = ['ui.show_side_bar', 'the sidebar is part of every page.', 'Set layout: wide or layout: full in a page\'s frontmatter for more room.'];
        }

        if (isset($config['seo']['author']) || isset($config['seo']['keywords']) || isset($config['seo']['og'])) {
            $dropped[] = ['seo.author, seo.keywords, seo.og', 'pages describe themselves through frontmatter (title, description), canonical URLs and JSON-LD.', 'Give every page a description in its frontmatter.'];
        }

        if (! empty($config['forum']['enabled'])) {
            $dropped[] = ['forum (Disqus)', 'third-party comment widgets are gone.', 'The built-in feedback widget sends a FeedbackSubmitted event you can forward anywhere.'];
        }

        if (isset($config['blade-parser'])) {
            $dropped[] = ['blade-parser', 'v3 does not pre-process HTML with regular expressions.', null];
        }

        return $dropped;
    }

    /** @param list<string> $middleware */
    private function php(V2Project $project, array $middleware, string $mode): string
    {
        $list = '['.implode(', ', array_map(fn (string $item) => var_export($item, true), $middleware)).']';

        return <<<PHP
            <?php

            // Written by php artisan larecipe:upgrade from your LaRecipe 2 config.
            // Content and presentation (versions, theme, navigation) now live next to the
            // docs in larecipe.json and navigation.json. The full reference for this file
            // is the package's own config/larecipe.php.

            return [

                'default' => 'docs',

                'sets' => [
                    'docs' => [
                        'route' => [
                            'prefix' => {$this->string($project->routePrefix())},
                            'domain' => null,
                            'middleware' => {$list},
                        ],
                        'source' => [
                            'driver' => 'repository',
                            'path' => base_path({$this->string($project->targetDocsPath())}),
                        ],
                        'access' => [
                            'mode' => {$this->string($mode)},
                        ],
                    ],
                ],

            ];

            PHP;
    }

    private function string(string $value): string
    {
        return var_export($value, true);
    }
}
