<?php

namespace LaRecipe\Domains\Sources\Drivers;

use DateTimeImmutable;
use FilesystemIterator;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\DTOs\Asset;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Docs that live in the app's own repository (recommended).
 *
 * Layout:
 *   docs/larecipe.json                      the set manifest
 *   docs/{version}/navigation.json          optional; derived from folders when absent
 *   docs/{version}/**\/*.md                 pages in the default locale
 *   docs/{version}/{locale}/**\/*.md        pages in another locale
 *   docs/{version}/_assets/**               images and other files
 *
 * A set without declared versions keeps its pages directly in docs/.
 */
class RepositorySource implements ContentSource
{
    private ?Manifest $manifest = null;

    private bool $manifestLoaded = false;

    public function __construct(
        protected readonly string $set,
        protected readonly string $root,
        protected readonly FrontmatterParser $parser = new FrontmatterParser,
    ) {}

    public function root(): string
    {
        return rtrim($this->root, '/');
    }

    public function manifest(): ?Manifest
    {
        if (! $this->manifestLoaded) {
            $this->manifestLoaded = true;
            $file = $this->root().'/larecipe.json';
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->manifest = is_array($data) ? Manifest::fromArray($data, ucfirst($this->set)) : null;
        }

        return $this->manifest;
    }

    public function pages(string $version, string $locale): iterable
    {
        $base = $this->localeRoot($version, $locale);

        if (! is_dir($base)) {
            return [];
        }

        $refs = [];

        foreach ($this->markdownFiles($base, $this->excludedDirectories($locale)) as $relative) {
            $refs[] = new PageRef($this->set, $version, $locale, PageRef::normalizePath($relative));
        }

        usort($refs, fn (PageRef $a, PageRef $b) => strcmp($a->path, $b->path));

        return $refs;
    }

    public function page(PageRef $ref): ?Page
    {
        $file = $this->pageFile($ref);

        if ($file === null) {
            return null;
        }

        [$frontmatter, $body] = $this->parser->parse((string) file_get_contents($file));

        return Page::make($ref, $frontmatter, $body, (new DateTimeImmutable)->setTimestamp((int) filemtime($file)));
    }

    /**
     * The absolute path of a page's Markdown file, or null when it does not exist.
     */
    public function pageFile(PageRef $ref): ?string
    {
        $base = $this->localeRoot($ref->version, $ref->locale);
        $candidates = $ref->isIndex()
            ? [$base.'/index.md', $base.'/README.md']
            : [$base.'/'.$ref->path.'.md', $base.'/'.$ref->path.'/index.md'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && $this->isInside($candidate, $base)) {
                return $candidate;
            }
        }

        return null;
    }

    public function navigation(string $version, string $locale): ?NavigationTree
    {
        $candidates = [
            $this->localeRoot($version, $locale).'/navigation.json',
            $this->versionRoot($version).'/navigation.json',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);

                return is_array($data) ? NavigationTree::fromJson($data) : null;
            }
        }

        return null;
    }

    public function asset(string $version, string $path): ?Asset
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        foreach ([$this->versionRoot($version), $this->versionRoot($version).'/_assets', $this->root().'/_assets'] as $base) {
            $file = $base.'/'.$path;

            if (is_file($file) && $this->isInside($file, $this->root()) && ! str_ends_with($file, '.md')) {
                return new Asset($path, self::mime($file), $file, null, (int) filesize($file), hash_file('sha256', $file) ?: null);
            }
        }

        return null;
    }

    public function fingerprint(?string $version = null): string
    {
        $base = $version === null ? $this->root() : $this->versionRoot($version);
        $parts = [];

        if (is_dir($base)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (in_array($file->getExtension(), ['md', 'json', 'css'], true)) {
                    $parts[] = $file->getPathname().':'.$file->getMTime().':'.$file->getSize();
                }
            }
        }

        sort($parts);
        $manifest = $this->root().'/larecipe.json';
        $parts[] = is_file($manifest) ? (string) filemtime($manifest) : '';

        return substr(hash('xxh128', implode("\n", $parts)), 0, 16);
    }

    public function versionRoot(string $version): string
    {
        $manifest = $this->manifest();

        if ($version === Version::IMPLICIT || $manifest === null || ! $manifest->isVersioned()) {
            return $this->root();
        }

        return $this->root().'/'.$version;
    }

    public function localeRoot(string $version, string $locale): string
    {
        $versionRoot = $this->versionRoot($version);

        return $this->isDefaultLocale($locale) ? $versionRoot : $versionRoot.'/'.$locale;
    }

    /** @return list<string> paths relative to $base, without leading slash */
    protected function markdownFiles(string $base, array $excluded): array
    {
        $files = [];
        $directory = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($directory, function (\SplFileInfo $file) use ($base, $excluded) {
            $relative = ltrim(substr($file->getPathname(), strlen($base)), '/');

            if ($file->isDir()) {
                $name = $file->getFilename();

                return ! str_starts_with($name, '.') && ! str_starts_with($name, '_')
                    && ! in_array($name, ['node_modules', 'vendor', 'skills'], true)
                    && ! in_array($relative, $excluded, true);
            }

            return strtolower($file->getExtension()) === 'md' && ! str_starts_with($file->getFilename(), '_');
        });

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            $relative = ltrim(substr($file->getPathname(), strlen($base)), '/');

            if (strcasecmp(basename($relative), 'README.md') === 0 && dirname($relative) !== '.') {
                continue;
            }

            $files[] = $relative;
        }

        return $files;
    }

    /** @return list<string> top-level folders that are not pages of this locale */
    protected function excludedDirectories(string $locale): array
    {
        $manifest = $this->manifest();
        $excluded = [];

        if ($manifest !== null && $this->isDefaultLocale($locale)) {
            foreach ($manifest->locales as $other) {
                if (! $other->default) {
                    $excluded[] = $other->code;
                }
            }
        }

        return $excluded;
    }

    protected function isDefaultLocale(string $locale): bool
    {
        $manifest = $this->manifest();

        if ($manifest === null || $manifest->locales === []) {
            return true;
        }

        foreach ($manifest->locales as $candidate) {
            if ($candidate->code === $locale) {
                return $candidate->default;
            }
        }

        return true;
    }

    protected function isInside(string $file, string $base): bool
    {
        $real = realpath($file);
        $realBase = realpath($base);

        return $real !== false && $realBase !== false && str_starts_with($real, $realBase.DIRECTORY_SEPARATOR);
    }

    protected static function mime(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            'woff2' => 'font/woff2',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
