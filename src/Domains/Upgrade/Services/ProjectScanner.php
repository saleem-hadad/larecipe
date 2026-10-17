<?php

namespace LaRecipe\Domains\Upgrade\Services;

use FilesystemIterator;
use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\DTOs\V2Project;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds what lives outside the docs folder and cannot be converted mechanically:
 * custom scripts and styles, published views, theme and asset packages, v2
 * add-on packages, the old namespace and the viewLarecipe gate. Detection only:
 * nothing here is rewritten.
 */
final class ProjectScanner
{
    /** v2 add-on packages and what replaces each one in v3. */
    private const ADDONS = [
        'binarytorch/larecipe-dark-theme' => 'Dark mode is built in and follows the reader\'s system setting.',
        'binarytorch/larecipe-rtl' => 'RTL is built in: give the locale "dir": "rtl" (Arabic, Hebrew and Persian get it automatically).',
        'binarytorch/larecipe-feedback' => 'The feedback widget is built in and sends a FeedbackSubmitted event.',
        'binarytorch/larecipe-swagger' => 'OpenAPI pages (type: openapi) arrive in a later 3.x release; link to the spec until then.',
    ];

    /** @return array{entries: list<ReportEntry>, gated: bool, composer: list<string>} */
    public function scan(V2Project $project): array
    {
        $entries = [];
        $gated = false;

        foreach ($this->phpFiles($project) as $relative => $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
                if (preg_match('/LaRecipe::(script|style)\s*\(/', $line, $matches)) {
                    $entries[] = ReportEntry::manual('assets', "LaRecipe::{$matches[1]}() is gone.", $relative, $number + 1, 'List CSS under assets.css in larecipe.json, or ship behavior as a React extension (larecipe-upgrade-v2 skill).');
                }

                if (str_contains($line, 'BinaryTorch\\LaRecipe')) {
                    $entries[] = ReportEntry::manual('namespace', 'The BinaryTorch\\LaRecipe namespace is now LaRecipe\\.', $relative, $number + 1, 'Replace BinaryTorch\\LaRecipe\\ with LaRecipe\\ and check the class still exists in v3.');
                }

                if (preg_match('/[\'"]viewLarecipe[\'"]/', $line)) {
                    $gated = true;
                    $entries[] = ReportEntry::manual('gate', 'The viewLarecipe gate now receives the page (a PageSummary with ref, title and private) and the set key, not the v2 documentation object.', $relative, $number + 1, 'Change the closure to fn ($user, $page = null, $set = null) and read $page?->ref->path or $page?->ref->version.');
                }
            }
        }

        foreach ($this->filesIn($project->path('resources/views/vendor/larecipe')) as $view) {
            $entries[] = ReportEntry::manual('views', 'Published v2 views are not used by v3.', 'resources/views/vendor/larecipe/'.$view, null, 'Replace it with a slot override (NotFound, Logo, Header, Sidebar) or LaRecipe::head(), then delete the folder.');
        }

        $packages = trim((string) ($project->config['packages']['path'] ?? 'larecipe-components'), '/');

        if ($packages !== '' && is_dir($project->path($packages))) {
            $entries[] = ReportEntry::manual('themes', "Theme and asset packages in {$packages}/ do not load in v3.", $packages, null, 'Port colors to theme tokens in larecipe.json or a theme.css, and scripts to a React extension built with @larecipe/vite.');
        }

        return ['entries' => $entries, 'gated' => $gated, 'composer' => $this->composer($project, $entries)];
    }

    /** @param list<ReportEntry> $entries */
    private function composer(V2Project $project, array &$entries): array
    {
        $composer = is_file($project->path('composer.json')) ? json_decode((string) file_get_contents($project->path('composer.json')), true) : null;
        $require = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require-dev'] ?? []));
        $commands = [];

        if (isset($require['binarytorch/larecipe'])) {
            $commands[] = 'composer remove binarytorch/larecipe';
        }

        foreach (self::ADDONS as $package => $replacement) {
            if (isset($require[$package])) {
                $commands[] = "composer remove {$package}";
                $entries[] = ReportEntry::dropped('packages', "{$package}: {$replacement}", 'composer.json');
            }
        }

        if (! isset($require['larecipe/larecipe'])) {
            $commands[] = 'composer require larecipe/larecipe';
        }

        return $commands;
    }

    /** @return array<string, string> relative path => absolute path, for app/, routes/ and config/ */
    private function phpFiles(V2Project $project): array
    {
        $files = [];

        foreach (['app', 'routes', 'config', 'bootstrap/app.php', 'bootstrap/providers.php'] as $folder) {
            $path = $project->path($folder);

            if (is_file($path)) {
                $files[$folder] = $path;

                continue;
            }

            foreach ($this->filesIn($path) as $relative) {
                if (str_ends_with($relative, '.php') && $relative !== 'larecipe.php') {
                    $files[$folder.'/'.$relative] = $path.'/'.$relative;
                }
            }
        }

        ksort($files);

        return $files;
    }

    /** @return list<string> paths relative to $directory */
    private function filesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $files[] = ltrim(substr($file->getPathname(), strlen($directory)), '/');
            }
        }

        sort($files);

        return $files;
    }
}
