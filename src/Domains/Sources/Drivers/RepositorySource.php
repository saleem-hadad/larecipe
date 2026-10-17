<?php

namespace LaRecipe\Domains\Sources\Drivers;

use DateTimeImmutable;
use FilesystemIterator;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\DTOs\Asset;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Exceptions\UnsafePath;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Support\MimeTypes;
use LaRecipe\Domains\Sources\Support\PathGuard;
use LaRecipe\Support\Contracts\Clock;
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
 *
 * Writes go to the working tree, so they become part of the developer's next
 * commit and the pull request; the write mode has no effect here because the
 * PR is the review. Every path is checked to stay inside the set's root.
 *
 * Provenance comes from a compiled index (CompileProvenanceAction, run by
 * larecipe:optimize or larecipe:provenance) when one exists, so production
 * never runs git per request.
 */
class RepositorySource implements WritableContentSource
{
    private ?Manifest $manifest = null;

    private bool $manifestLoaded = false;

    /** @var array<string, array>|null provenance entries keyed by path relative to the root */
    private ?array $provenance = null;

    public function __construct(
        protected readonly string $set,
        protected readonly string $root,
        protected readonly FrontmatterParser $parser = new FrontmatterParser,
        protected readonly ?string $provenanceIndex = null,
        protected readonly ?Clock $clock = null,
    ) {}

    public function root(): string
    {
        return rtrim($this->root, '/');
    }

    public function set(): string
    {
        return $this->set;
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
        $provenance = $this->provenanceFor($file);

        return Page::make(
            $ref,
            $frontmatter,
            $body,
            $provenance?->at ?? (new DateTimeImmutable)->setTimestamp((int) filemtime($file)),
            $provenance,
        );
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
        $file = $this->navigationFile($version, $locale);

        if ($file === null) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? NavigationTree::fromJson($data) : null;
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

    public function writePage(Page $page, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        $file = $this->targetFile($page->ref);
        $this->writeFile($file, $this->parser->compose($page->frontmatter, $page->body));

        return $this->revision(SubjectType::Page, RevisionOperation::Write, $by, $context, $page->contentHash, $page->ref, null, [
            'frontmatter' => $page->frontmatter->toArray(),
            'body' => $page->body,
        ]);
    }

    public function movePage(PageRef $from, PageRef $to, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        $source = $this->pageFile($from) ?? throw InvalidContentChange::missingPage($from->path);

        if ($this->pageFile($to) !== null) {
            throw InvalidContentChange::pathTaken($to->path);
        }

        $page = $this->page($from);
        $target = $this->targetFile($to);
        $this->ensureDirectory(dirname($target));
        rename($source, $target);

        return $this->revision(SubjectType::Page, RevisionOperation::Move, $by, $context, $page?->contentHash, $to, $from, [
            'frontmatter' => $page?->frontmatter->toArray() ?? [],
            'body' => $page?->body ?? '',
            'from' => $from->toArray(),
        ]);
    }

    public function deletePage(PageRef $ref, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        $file = $this->pageFile($ref) ?? throw InvalidContentChange::missingPage($ref->path);
        $page = $this->page($ref);
        unlink($file);

        return $this->revision(SubjectType::Page, RevisionOperation::Delete, $by, $context, null, $ref, null, [
            'frontmatter' => $page?->frontmatter->toArray() ?? [],
            'body' => $page?->body ?? '',
        ]);
    }

    public function writeNavigation(string $version, string $locale, NavigationTree $tree, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        $file = $this->localeRoot(PathGuard::segment($version), PathGuard::segment($locale)).'/navigation.json';
        $existing = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $data = $tree->toJson();

        if (is_array($existing) && isset($existing['$schema'])) {
            $data = ['$schema' => $existing['$schema']] + $data;
        }

        $this->writeFile($file, self::json($data));

        return $this->revision(SubjectType::Navigation, RevisionOperation::Write, $by, $context, ContentHashes::navigation($tree), null, null, ['tree' => $tree->toJson()], $version, $locale);
    }

    public function removeNavigation(string $version, string $locale, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        $file = $this->localeRoot(PathGuard::segment($version), PathGuard::segment($locale)).'/navigation.json';

        if (is_file($file) && $this->isInside($file, $this->root())) {
            unlink($file);
        }

        return $this->revision(SubjectType::Navigation, RevisionOperation::Delete, $by, $context, null, null, null, [], $version, $locale);
    }

    public function writeManifest(Manifest $manifest, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        $this->writeFile($this->root().'/larecipe.json', self::json($manifest->toArray()));
        $this->manifest = null;
        $this->manifestLoaded = false;

        return $this->revision(SubjectType::Manifest, RevisionOperation::Write, $by, $context, ContentHashes::manifest($manifest), null, null, ['manifest' => $manifest->toArray()]);
    }

    public function writeAsset(string $version, string $path, string $contents): void
    {
        $this->writeFile($this->assetFile($version, $path), $contents);
    }

    public function deleteAsset(string $version, string $path): void
    {
        $file = $this->assetFile($version, $path);

        if (is_file($file) && $this->isInside($file, $this->root())) {
            unlink($file);
        }
    }

    public function assets(string $version): iterable
    {
        $base = $this->versionRoot($version);

        if (! is_dir($base)) {
            return [];
        }

        $manifest = $this->manifest();
        $skipTop = $version === Version::IMPLICIT && $manifest !== null && $manifest->isVersioned()
            ? array_map(fn (Version $v) => $v->id, $manifest->versions)
            : [];

        $directory = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($directory, function (\SplFileInfo $file) use ($base, $skipTop) {
            $relative = ltrim(substr($file->getPathname(), strlen($base)), '/');
            $name = $file->getFilename();

            if (str_starts_with($name, '.')) {
                return false;
            }

            if ($file->isDir()) {
                return ! in_array($name, ['node_modules', 'vendor', 'skills'], true) && ! in_array($relative, $skipTop, true);
            }

            return strtolower($file->getExtension()) !== 'md' && ! in_array($name, ['navigation.json', 'larecipe.json'], true);
        });

        $paths = [];

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            $paths[] = ltrim(substr($file->getPathname(), strlen($base)), '/');
        }

        sort($paths);

        return $paths;
    }

    /**
     * Every content file of the set (pages, navigation, manifest), relative to
     * the root, for the provenance index.
     *
     * @return list<string>
     */
    public function contentFiles(): array
    {
        if (! is_dir($this->root())) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($this->root(), FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $file) => ! str_starts_with($file->getFilename(), '.') && ! in_array($file->getFilename(), ['node_modules', 'vendor'], true),
        ));

        foreach ($iterator as $file) {
            if (in_array(strtolower($file->getExtension()), ['md', 'json'], true)) {
                $files[] = ltrim(substr($file->getPathname(), strlen($this->root())), '/');
            }
        }

        sort($files);

        return $files;
    }

    public function provenanceIndexPath(): ?string
    {
        return $this->provenanceIndex;
    }

    /** Drop the loaded provenance index, after it was recompiled. */
    public function forgetProvenance(): void
    {
        $this->provenance = null;
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
        return MimeTypes::for($file);
    }

    /**
     * Where a page is written: its existing file, or a new one. A new page whose
     * path is already a folder becomes that folder's index.md, so relative links
     * inside it keep resolving from the folder.
     */
    private function targetFile(PageRef $ref): string
    {
        $existing = $this->pageFile($ref);

        if ($existing !== null) {
            return $existing;
        }

        $base = $this->localeRoot(PathGuard::segment($ref->version), PathGuard::segment($ref->locale));

        if ($ref->isIndex()) {
            return $base.'/index.md';
        }

        $path = PathGuard::relative($ref->path);

        return is_dir($base.'/'.$path) ? $base.'/'.$path.'/index.md' : $base.'/'.$path.'.md';
    }

    private function navigationFile(string $version, string $locale): ?string
    {
        foreach ([$this->localeRoot($version, $locale).'/navigation.json', $this->versionRoot($version).'/navigation.json'] as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private function assetFile(string $version, string $path): string
    {
        $path = PathGuard::relative($path);
        $name = strtolower(basename($path));

        if (str_ends_with($name, '.md') || in_array($name, ['navigation.json', 'larecipe.json'], true)) {
            throw UnsafePath::for($path);
        }

        return $this->versionRoot(PathGuard::segment($version)).'/'.$path;
    }

    private function writeFile(string $file, string $contents): void
    {
        $this->ensureDirectory(dirname($file));

        if (! PathGuard::isInside($file, $this->root())) {
            throw UnsafePath::for($file);
        }

        file_put_contents($file, $contents, LOCK_EX);
        clearstatcache(true, $file);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($this->root())) {
            mkdir($this->root(), 0755, true);
        }

        if (! PathGuard::isInside($directory, $this->root())) {
            throw UnsafePath::for($directory);
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function provenanceFor(string $file): ?Provenance
    {
        if ($this->provenanceIndex === null) {
            return null;
        }

        if ($this->provenance === null) {
            $index = is_file($this->provenanceIndex) ? require $this->provenanceIndex : [];
            $this->provenance = is_array($index['files'] ?? null) ? $index['files'] : [];
        }

        $root = realpath($this->root());
        $real = realpath($file);
        $relative = $root !== false && $real !== false ? ltrim(substr($real, strlen($root)), '/') : null;
        $entry = $relative !== null ? ($this->provenance[$relative] ?? null) : null;

        return is_array($entry) ? Provenance::fromArray($entry) : null;
    }

    private function revision(
        SubjectType $type,
        RevisionOperation $operation,
        Provenance $by,
        ?WriteContext $context,
        ?string $hash,
        ?PageRef $ref,
        ?PageRef $before,
        array $payload,
        ?string $version = null,
        ?string $locale = null,
    ): Revision {
        return new Revision(
            id: null,
            subjectType: $type->value,
            subject: Revision::subjectKey($type, $ref, $version, $locale),
            operation: $operation->value,
            status: RevisionStatus::Published->value,
            author: $by,
            contentHash: $hash,
            basedOnHash: $context?->basedOn,
            summary: $context?->summary,
            createdAt: $this->clock?->now() ?? new DateTimeImmutable,
            set: $this->set,
            ref: $ref,
            refBefore: $before,
            payload: $payload,
            channel: $context?->channel->value,
            migration: $context?->migration,
            publishedAt: $this->clock?->now() ?? new DateTimeImmutable,
        );
    }

    /** JSON the way people format it: two-space indents, no escaped slashes or Unicode, a final newline. */
    private static function json(array $data): string
    {
        if ($data === []) {
            return "{}\n";
        }

        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return preg_replace_callback('/^( +)/m', fn (array $m) => str_repeat(' ', intdiv(strlen($m[1]), 2)), $json)."\n";
    }
}
