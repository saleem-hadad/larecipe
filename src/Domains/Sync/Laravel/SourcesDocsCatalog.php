<?php

namespace LaRecipe\Domains\Sync\Laravel;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Locale;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\Enums\ReviewMode;
use LaRecipe\LaRecipe;
use Throwable;

/**
 * The docs catalog, read through the Sources domain.
 *
 * Repository sets report each page's Markdown file, so a change to that file
 * counts as "the page was updated". Database sets have no files: their pages
 * come from the configured source (the local database, refreshed with
 * larecipe:pull) and their updates travel as docs migrations.
 */
final class SourcesDocsCatalog implements DocsCatalog
{
    /** @var array<string, list<CatalogPage>> */
    private array $pages = [];

    /** @var array<string, CatalogSet>|null */
    private ?array $sets = null;

    /**
     * @param  array<string, SetConfig>  $configs
     */
    public function __construct(
        private readonly LaRecipe $larecipe,
        private readonly SourceResolver $sources,
        private readonly array $configs,
        private readonly string $projectRoot,
        private readonly string $defaultMigrationsPath,
    ) {}

    public function sets(): array
    {
        if ($this->sets === null) {
            $this->sets = [];

            foreach ($this->configs as $key => $config) {
                $this->sets[$key] = $this->describe($config);
            }
        }

        return array_values($this->sets);
    }

    public function set(string $key): ?CatalogSet
    {
        $this->sets();

        return $this->sets[$key] ?? null;
    }

    public function pages(string $set, string $version, string $locale): array
    {
        return $this->pages["{$set}/{$version}/{$locale}"] ??= $this->load($set, $version, $locale);
    }

    /** @return list<CatalogPage> */
    private function load(string $set, string $version, string $locale): array
    {
        try {
            $source = $this->sources->for($set);
            $pages = [];

            foreach ($source->pages($version, $locale) as $ref) {
                $page = $source->page($ref);

                if ($page === null) {
                    continue;
                }

                $file = $source instanceof RepositorySource ? $source->pageFile($ref) : null;

                $pages[] = new CatalogPage(
                    ref: $ref,
                    title: $page->title,
                    sources: $page->sources(),
                    file: $file === null ? null : $this->relative($file),
                    updatedAt: $file === null ? $page->updatedAt : null,
                    description: $page->description,
                    hidden: $page->frontmatter->hidden(),
                    private: $page->frontmatter->visibility()->value === 'private',
                    body: $page->body,
                );
            }

            return $pages;
        } catch (Throwable) {
            // An unavailable source (a database set before its tables exist) has no pages to check.
            return [];
        }
    }

    private function describe(SetConfig $config): CatalogSet
    {
        $docSet = $this->docSet($config->key);
        $repository = $config->driver === 'repository';
        $path = $repository ? (string) ($config->source['path'] ?? $this->projectRoot.'/'.$config->key) : null;

        return new CatalogSet(
            key: $config->key,
            name: $docSet?->manifest->name ?? ucfirst($config->key),
            driver: $config->driver,
            docsPath: $path === null ? null : $this->relative($path),
            migrationsPath: $this->relative((string) ($config->setting('migrations.path') ?: $this->defaultMigrationsPath)),
            versions: $docSet === null ? [Version::IMPLICIT] : array_map(fn (Version $v) => $v->id, $docSet->versions()),
            defaultVersion: $docSet?->defaultVersion()->id ?? Version::IMPLICIT,
            locales: $docSet === null ? ['en'] : array_map(fn (Locale $l) => $l->code, $docSet->locales()),
            defaultLocale: $docSet?->defaultLocale()->code ?? 'en',
            review: ReviewMode::fromConfig($config->review),
            versioned: $docSet?->isVersioned() ?? false,
        );
    }

    private function docSet(string $key): ?DocSet
    {
        try {
            return $this->larecipe->set($key);
        } catch (Throwable) {
            return null;
        }
    }

    /** A path relative to the project root, or unchanged when it lies outside. */
    private function relative(string $path): string
    {
        $root = rtrim($this->projectRoot, '/');
        $real = realpath($path) ?: $path;
        $realRoot = realpath($root) ?: $root;

        foreach ([[$real, $realRoot], [$path, $root]] as [$candidate, $base]) {
            if ($candidate === $base) {
                return '';
            }

            if (str_starts_with($candidate, $base.'/')) {
                return substr($candidate, strlen($base) + 1);
            }
        }

        return $path;
    }
}
