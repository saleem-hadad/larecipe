<?php

namespace LaRecipe\Domains\Validation\Laravel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Locale;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Enums\Visibility;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Validation\Contracts\DocsInspector;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\SkillSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationSettings;
use LaRecipe\Domains\Validation\DTOs\VersionSnapshot;
use LaRecipe\Domains\Validation\Services\CollectingLinkResolver;
use LaRecipe\Domains\Validation\Services\LinkPaths;
use LaRecipe\Domains\Validation\Services\SnapshotLinkTargets;
use Throwable;

/**
 * Reads a set for validation through its source and the real renderer, so
 * validation sees exactly what readers and agents get.
 *
 * For a repository set it also reads the files as written (larecipe.json,
 * navigation.json, frontmatter), because a loader that forgives a typo would
 * hide exactly what validation exists to find.
 */
final class SourceInspector implements DocsInspector
{
    public function __construct(
        private readonly SetRegistry $sets,
        private readonly SourceResolver $sources,
        private readonly RenderPageAction $render,
        private readonly AgentMarkdownRenderer $markdown,
        private readonly FrontmatterParser $parser,
        private readonly ComponentRegistry $components,
        private readonly DocsUrls $urls,
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    public function sets(): array
    {
        return array_keys($this->sets->configs());
    }

    public function hasSet(string $set): bool
    {
        return $this->sets->has($set);
    }

    public function snapshot(string $set, ?string $version = null): DocsSnapshot
    {
        $docs = $this->sets->get($set);
        $source = $this->sources->for($set);
        [$manifest, $error] = $this->manifest($source);
        $versions = [];

        foreach ($docs->versions() as $candidate) {
            if ($version !== null && $candidate->id !== $version) {
                continue;
            }

            foreach ($docs->locales() as $locale) {
                $versions[] = $this->version($docs, $source, $candidate, $locale);
            }
        }

        return new DocsSnapshot($set, $manifest, $versions, $this->settings($docs), $this->skills($source), $error);
    }

    public function inspect(Page $page): PageSnapshot
    {
        $set = $this->sets->has($page->ref->set) ? $this->sets->get($page->ref->set) : null;
        $source = $set === null ? null : $this->sources->for($set->key);
        $file = $source instanceof RepositorySource ? $source->pageFile($page->ref) : null;

        return $this->pageSnapshot($set, $source, $page, $file, false);
    }

    public function context(Page $page): PageContext
    {
        $ref = $page->ref;

        if (! $this->sets->has($ref->set)) {
            $empty = new DocsSnapshot($ref->set, null, [], new ValidationSettings);

            return new PageContext($ref->version, $ref->locale, '', $this->settings(null), new SnapshotLinkTargets($empty), strictLinks: false);
        }

        $set = $this->sets->get($ref->set);

        return new PageContext(
            $ref->version,
            $ref->locale,
            $this->label($set, $ref->version, $ref->locale),
            $this->settings($set),
            new SourceLinkTargets($set, $this->sources->for($set->key), $this->render),
            $set->version($ref->version)?->visibility === Visibility::Private,
            strictLinks: false,
        );
    }

    private function version(DocSet $set, ContentSource $source, Version $version, Locale $locale): VersionSnapshot
    {
        $byPath = [];

        foreach ($source->pages($version->id, $locale->code) as $ref) {
            $byPath[$ref->path][] = $ref;
        }

        $pages = [];

        foreach ($byPath as $path => $refs) {
            $page = $source->page($refs[0]);

            if ($page !== null) {
                $file = $source instanceof RepositorySource ? $source->pageFile($refs[0]) : null;
                $pages[$path] = $this->pageSnapshot($set, $source, $page, $file, true);
            }
        }

        [$navigation, $navigationFile, $navigationError, $shared] = $this->navigation($source, $version->id, $locale);

        return new VersionSnapshot(
            version: $version->id,
            locale: $locale->code,
            label: $this->label($set, $version->id, $locale->code),
            defaultLocale: $locale->code === $set->defaultLocale()->code,
            private: $version->visibility === Visibility::Private,
            navigation: $navigation,
            navigationFile: $navigationFile,
            navigationError: $navigationError,
            sharedNavigation: $shared,
            pages: $pages,
            duplicates: $this->duplicates($source, $version->id, $locale->code, array_keys($byPath), $byPath),
        );
    }

    private function pageSnapshot(?DocSet $set, ?ContentSource $source, Page $page, ?string $file, bool $fromSource): PageSnapshot
    {
        $relative = $file !== null && $source instanceof RepositorySource ? $this->relative($source, $file) : null;
        $folderIndex = $file !== null && str_ends_with(str_replace('\\', '/', $file), '/'.$page->ref->path.'/index.md');
        [$yamlError, $offset] = $fromSource && $file !== null ? $this->frontmatterOf($file) : [null, 0];

        $rendered = null;
        $renderError = null;

        try {
            $rendered = $this->render->execute($page, new CollectingLinkResolver);
        } catch (Throwable $exception) {
            $renderError = $exception->getMessage();
        }

        try {
            $url = $set !== null ? $this->urls->page($set, $page->ref) : '/'.$page->ref->path;
            $size = mb_strlen($this->markdown->render($page, ['url' => $url]));
        } catch (Throwable) {
            $size = null;
        }

        return new PageSnapshot(
            page: $page,
            file: $relative,
            folderIndex: $folderIndex,
            rendered: $rendered,
            anchors: $rendered === null ? [] : LinkPaths::anchorsIn($rendered->html),
            markdownSize: $size,
            yamlError: $yamlError,
            renderError: $renderError,
            lineOffset: $offset,
        );
    }

    /** @return array{0: ?string, 1: int} the YAML error, and how many lines precede the body */
    private function frontmatterOf(string $file): array
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        [, $body, $error] = $this->parser->parse($raw);
        $prefix = substr($raw, 0, max(0, strlen($raw) - strlen($body)));

        return [$error, $body === $raw ? 0 : substr_count($prefix, "\n")];
    }

    /** @return array{0: ?array, 1: ?string} the manifest as written, and why it could not be read */
    private function manifest(ContentSource $source): array
    {
        if (! $source instanceof RepositorySource) {
            return [$source->manifest()?->toArray(), null];
        }

        $file = $source->root().'/larecipe.json';

        if (! is_file($file)) {
            return [null, null];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? [$data, null] : [null, json_last_error_msg()];
    }

    /** @return array{0: ?array, 1: ?string, 2: ?string, 3: bool} data, file, JSON error, borrowed from the version */
    private function navigation(ContentSource $source, string $version, Locale $locale): array
    {
        if (! $source instanceof RepositorySource) {
            return [$source->navigation($version, $locale->code)?->toJson(), null, null, false];
        }

        $own = $source->localeRoot($version, $locale->code).'/navigation.json';
        $shared = $source->versionRoot($version).'/navigation.json';
        $file = is_file($own) ? $own : (is_file($shared) ? $shared : null);

        if ($file === null) {
            return [null, null, null, false];
        }

        $data = json_decode((string) file_get_contents($file), true);
        $borrowed = $file !== $own;

        return is_array($data)
            ? [$data, $this->relative($source, $file), null, $borrowed]
            : [null, $this->relative($source, $file), json_last_error_msg(), $borrowed];
    }

    /**
     * Paths that more than one file maps to: "guides.md" next to
     * "guides/index.md", or names that differ only in case.
     *
     * @param  list<string>  $paths
     * @param  array<string, list<PageRef>>  $byPath
     * @return list<array{path: string, files: list<string>}>
     */
    private function duplicates(ContentSource $source, string $version, string $locale, array $paths, array $byPath): array
    {
        $duplicates = [];

        if ($source instanceof RepositorySource) {
            $base = $source->localeRoot($version, $locale);

            foreach ($paths as $path) {
                $files = $path === 'index' ? [] : array_values(array_filter([$base.'/'.$path.'.md', $base.'/'.$path.'/index.md'], 'is_file'));

                if (count($files) > 1) {
                    $duplicates[] = ['path' => $path, 'files' => array_map(fn (string $f) => $this->relative($source, $f), $files)];
                }
            }
        }

        $folded = [];

        foreach ($paths as $path) {
            $folded[strtolower($path)][] = $path;
        }

        foreach ($folded as $variants) {
            if (count($variants) > 1) {
                $files = array_map(fn (string $path) => $source instanceof RepositorySource ? $this->relative($source, (string) $source->pageFile($byPath[$path][0])) : $path, $variants);
                $duplicates[] = ['path' => strtolower($variants[0]), 'files' => $files];
            }
        }

        return $duplicates;
    }

    /** @return list<SkillSnapshot> */
    private function skills(ContentSource $source): array
    {
        if (! $source instanceof RepositorySource || ! is_dir($source->root().'/skills')) {
            return [];
        }

        $skills = [];

        foreach (glob($source->root().'/skills/*/SKILL.md') ?: [] as $file) {
            $skills[] = new SkillSnapshot(basename(dirname($file)), $this->relative($source, $file), (string) file_get_contents($file));
        }

        return $skills;
    }

    private function settings(?DocSet $set): ValidationSettings
    {
        $store = $this->config->get('larecipe.cache.store') ?? $this->config->get('cache.default');
        $driver = $set?->config->driver ?? 'repository';
        $snippets = $set?->config->setting('snippets.root');

        return new ValidationSettings(
            prefix: $set?->config->prefix ?? '',
            driver: $driver,
            cacheStore: is_string($store) ? $this->config->get("cache.stores.{$store}.driver", $store) : null,
            searchDriver: (string) ($set?->config->setting('search.driver', 'client') ?? 'client'),
            markdownBudget: (int) ($set?->config->setting('agents.markdown_budget', 50000) ?? 50000),
            searchBudget: (int) ($set?->config->setting('agents.search_budget', 300000) ?? 300000),
            knownComponents: array_keys($this->components->all()),
            snippetRoot: is_string($snippets) ? $snippets : ($driver === 'repository' ? $this->app->basePath() : null),
            testing: $this->app->environment('testing'),
        );
    }

    /** The URL segments between the prefix and a page path: "", "2.x", "ar/2.x". */
    private function label(DocSet $set, string $version, string $locale): string
    {
        $segments = [];

        if ($set->isMultilingual() && $locale !== $set->defaultLocale()->code) {
            $segments[] = $locale;
        }

        if ($set->isVersioned()) {
            $segments[] = $version;
        }

        return implode('/', $segments);
    }

    private function relative(RepositorySource $source, string $file): string
    {
        $root = realpath($source->root()) ?: $source->root();
        $real = realpath($file) ?: $file;

        return str_starts_with($real, $root.'/') ? substr($real, strlen($root) + 1) : $file;
    }
}
