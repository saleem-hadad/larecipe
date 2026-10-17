<?php

namespace LaRecipe\Domains\Upgrade\Actions;

use FilesystemIterator;
use InvalidArgumentException;
use LaRecipe\Domains\Upgrade\DTOs\ConvertedPage;
use LaRecipe\Domains\Upgrade\DTOs\PlannedFile;
use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\DTOs\UpgradePlan;
use LaRecipe\Domains\Upgrade\DTOs\V2Project;
use LaRecipe\Domains\Upgrade\Services\ConfigMapper;
use LaRecipe\Domains\Upgrade\Services\PageRewriter;
use LaRecipe\Domains\Upgrade\Services\ProjectScanner;
use LaRecipe\Domains\Upgrade\Services\SidebarParser;
use LaRecipe\Domains\Upgrade\Services\V2Links;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Works out the whole v2 to v3 upgrade without writing anything: the config and
 * manifest, each version's navigation.json, every rewritten page, the files a
 * move would carry, and the report entries for what a person must finish.
 *
 * `larecipe:upgrade` shows this plan as a dry run, and applies the same plan with
 * --apply, so what the report promises is exactly what gets written.
 */
final class PlanUpgradeAction
{
    public function __construct(
        private readonly ConfigMapper $config,
        private readonly SidebarParser $sidebar,
        private readonly PageRewriter $rewriter,
        private readonly ProjectScanner $scanner,
    ) {}

    public function execute(V2Project $project): UpgradePlan
    {
        $source = $project->docsPath();
        $target = $project->targetDocsPath();
        $moving = $source !== $target;

        if (! is_dir($project->path($source))) {
            throw new InvalidArgumentException("There are no docs at {$source}/ (docs.path in the v2 config).");
        }

        if ($moving && is_dir($project->path($target)) && (new FilesystemIterator($project->path($target)))->valid()) {
            throw new InvalidArgumentException("{$target}/ already has files in it. Move them aside, or pick another folder with --move-to.");
        }

        $scan = $this->scanner->scan($project);
        $mapped = $this->config->map($project, $scan['gated']);
        $links = new V2Links($project->routePrefix(), $project->relativeLinks, (string) ($project->config['docs']['landing'] ?? 'overview'));
        $entries = [...$mapped['entries']];
        $files = [];
        $pages = [];
        $navigation = [];
        $carried = [];

        foreach ($project->versions() as $version) {
            $folder = $project->path("{$source}/{$version}");

            if (! is_dir($folder)) {
                $entries[] = ReportEntry::manual('versions', "Version {$version} is published in the v2 config but has no folder.", "{$source}/{$version}");

                continue;
            }

            $all = $this->files($folder);
            $pageFiles = array_values(array_filter($all, fn (string $file) => str_ends_with(strtolower($file), '.md') && $file !== 'index.md'));
            $paths = [];

            foreach ($pageFiles as $file) {
                $paths[$this->pagePath($file)] = $file;
            }

            $titles = [];

            foreach ($pageFiles as $file) {
                $page = $this->page($project, $version, $file, $links, $paths, $entries);
                $pages[] = $page;
                $titles[$page->path] = (string) ($page->frontmatter['title'] ?? '');

                if ($page->changed || $moving) {
                    $files[] = PlannedFile::write("{$target}/{$version}/{$file}", $this->compose($page), $page->changed ? 'converted to v3 Markdown' : 'moved');
                }

                if ($moving) {
                    $carried[] = "{$source}/{$version}/{$file}";
                }

                if (strcasecmp($file, 'README.md') === 0) {
                    $entries[] = ReportEntry::manual('pages', 'A README.md in a version folder is served as a page in v3.', "{$target}/{$version}/{$file}", null, 'Delete it if it is not part of the docs.');
                }
            }

            if (in_array('index.md', $all, true)) {
                $parsed = $this->sidebar->parse((string) file_get_contents("{$folder}/index.md"), $version, $links, $titles, "{$source}/{$version}/index.md");
                $navigation[$version] = $parsed['navigation'];
                array_push($entries, ...$parsed['entries']);
                $files[] = PlannedFile::write("{$target}/{$version}/navigation.json", json_encode($parsed['navigation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", 'the v2 sidebar as navigation.json');
                $files[] = PlannedFile::delete("{$source}/{$version}/index.md", 'the sidebar now lives in navigation.json');
            } else {
                $entries[] = ReportEntry::changed('navigation', "Version {$version} has no sidebar (index.md), so v3 derives navigation from folders and frontmatter.", "{$source}/{$version}");
            }

            foreach (array_diff($all, $pageFiles, ['index.md']) as $asset) {
                if ($moving) {
                    $files[] = PlannedFile::copy("{$source}/{$version}/{$asset}", "{$target}/{$version}/{$asset}");
                    $carried[] = "{$source}/{$version}/{$asset}";
                }
            }
        }

        if ($moving) {
            foreach ($this->files($project->path($source)) as $file) {
                $versionFolder = explode('/', $file)[0];

                if (! in_array($versionFolder, $project->versions(), true)) {
                    $files[] = PlannedFile::copy("{$source}/{$file}", "{$target}/{$file}");
                    $carried[] = "{$source}/{$file}";
                }
            }

            foreach ($carried as $old) {
                $files[] = PlannedFile::delete($old, "moved to {$target}/");
            }

            $entries[] = ReportEntry::changed('layout', "The docs move from {$source}/ to {$target}/.", $target);
        }

        $files[] = PlannedFile::write("{$target}/larecipe.json", json_encode($mapped['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", 'the set manifest');
        $files[] = PlannedFile::write('config/larecipe.php', $mapped['config'], 'the LaRecipe 3 config (the v2 file is kept as config/larecipe.v2.php.bak)');
        array_push($entries, ...$scan['entries']);

        return new UpgradePlan($project, $files, $pages, $mapped['manifest'], $navigation, $mapped['config'], $entries, $scan['composer']);
    }

    /** @param list<ReportEntry> $entries */
    private function page(V2Project $project, string $version, string $file, V2Links $links, array $paths, array &$entries): ConvertedPage
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($project->path("{$project->docsPath()}/{$version}/{$file}")));
        [$frontmatter, $body] = $this->frontmatter($raw);

        $result = $this->rewriter->rewrite($body, $version, $file, "{$project->targetDocsPath()}/{$version}/{$file}", $links, $paths);
        array_push($entries, ...$result['entries']);

        $seeded = array_filter(['title' => $result['title'], 'description' => $result['description']], fn ($value) => $value !== null);
        $frontmatter += $seeded;

        $page = new ConvertedPage($version, $this->pagePath($file), $file, $frontmatter, $result['body'], true);

        return new ConvertedPage($page->version, $page->path, $page->file, $page->frontmatter, $page->body, $this->compose($page) !== $raw);
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function frontmatter(string $raw): array
    {
        if (! preg_match('/\A---[ \t]*\n(.*?)\n---[ \t]*(?:\n|\z)/s', $raw, $matches)) {
            return [[], $raw];
        }

        try {
            $data = Yaml::parse($matches[1]);
        } catch (ParseException) {
            return [[], $raw];
        }

        return [is_array($data) ? $data : [], ltrim(substr($raw, strlen($matches[0])), "\n")];
    }

    private function compose(ConvertedPage $page): string
    {
        if ($page->frontmatter === []) {
            return $page->body;
        }

        return "---\n".Yaml::dump($page->frontmatter, 2, 2)."---\n\n".ltrim($page->body, "\n");
    }

    /** "guides/index.md" is the page "guides"; "overview.md" is "overview". */
    private function pagePath(string $file): string
    {
        $path = (string) preg_replace('/\.md$/i', '', $file);
        $path = (string) preg_replace('#(^|/)index$#', '', $path);

        return trim($path, '/') === '' ? 'index' : trim($path, '/');
    }

    /** @return list<string> every file under a folder, relative to it, hidden files left out */
    private function files(string $folder): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = ltrim(substr($file->getPathname(), strlen($folder)), '/');

            if ($file->isFile() && ! str_starts_with(basename($relative), '.')) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }
}
