<?php

namespace LaRecipe\Domains\Sources\Drivers;

use Closure;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Contracts\ContentCache;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\DTOs\Asset;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Services\PagePayload;
use LaRecipe\Support\CacheFormat;
use Throwable;
use UnexpectedValueException;

/**
 * The cache every database set runs behind (the Cached-Database Rule): a warm
 * request for a cached page never queries the database.
 *
 * The set's fingerprint lives in the cache store and is re-read from the
 * database after every write, publish, reject or restore. The manifest, page
 * lists, pages, navigation and small assets are cached under that fingerprint
 * with no expiry, so a new fingerprint makes every old entry unreachable at
 * once and nothing is ever purged by hand.
 *
 * Review data (drafts, revisions, history) is not cached: only the admin UI
 * reads it, and it must always be current.
 */
final class CachedSource implements DraftableContentSource
{
    /** Assets above this size are read from their disk on every request instead of the cache. */
    public const MAX_CACHED_ASSET_BYTES = 1048576;

    public function __construct(
        private readonly DraftableContentSource $inner,
        private readonly ContentCache $cache,
        private readonly string $set,
    ) {}

    public function inner(): DraftableContentSource
    {
        return $this->inner;
    }

    public function fingerprint(?string $version = null): string
    {
        $cached = $this->cache->get($this->fingerprintKey());

        return is_string($cached) && $cached !== '' ? $cached : $this->refresh();
    }

    /** Read the fingerprint from the source and store it. Every change calls this. */
    public function refresh(): string
    {
        $fingerprint = $this->inner->fingerprint();
        $this->cache->forever($this->fingerprintKey(), $fingerprint);

        return $fingerprint;
    }

    public function manifest(): ?Manifest
    {
        return $this->remember(
            'manifest',
            fn () => $this->inner->manifest()?->toArray(),
            fn (mixed $data) => $data === null ? null : Manifest::fromArray(self::shape($data), ucfirst($this->set)),
        );
    }

    public function pages(string $version, string $locale): iterable
    {
        return $this->remember(
            "pages|{$version}|{$locale}",
            function () use ($version, $locale) {
                $paths = [];

                foreach ($this->inner->pages($version, $locale) as $ref) {
                    $paths[] = $ref->path;
                }

                return $paths;
            },
            fn (mixed $paths) => array_map(fn (string $path) => new PageRef($this->set, $version, $locale, $path), self::shape($paths)),
        );
    }

    public function page(PageRef $ref): ?Page
    {
        return $this->remember(
            "page|{$ref->version}|{$ref->locale}|{$ref->path}",
            function () use ($ref) {
                $page = $this->inner->page($ref);

                return $page !== null ? PagePayload::toArray($page) : null;
            },
            fn (mixed $data) => $data === null ? null : PagePayload::fromArray(self::shape($data)),
        );
    }

    public function navigation(string $version, string $locale): ?NavigationTree
    {
        return $this->remember(
            "navigation|{$version}|{$locale}",
            fn () => $this->inner->navigation($version, $locale)?->toJson(),
            fn (mixed $tree) => $tree === null ? null : NavigationTree::fromJson(self::shape($tree)),
        );
    }

    public function asset(string $version, string $path): ?Asset
    {
        return $this->remember(
            "asset|{$version}|{$path}",
            function () use ($version, $path) {
                $asset = $this->inner->asset($version, $path);

                if ($asset === null) {
                    return null;
                }

                $contents = $asset->contents();

                return strlen($contents) > self::MAX_CACHED_ASSET_BYTES
                    ? ['large' => true]
                    : ['path' => $asset->path, 'mime' => $asset->mime, 'contents' => base64_encode($contents), 'size' => $asset->size, 'hash' => $asset->hash];
            },
            function (mixed $data) use ($version, $path) {
                if ($data === null) {
                    return null;
                }

                $data = self::shape($data);

                return isset($data['large'])
                    ? $this->inner->asset($version, $path)
                    : new Asset($data['path'], $data['mime'], null, (string) base64_decode((string) $data['contents']), $data['size'], $data['hash']);
            },
        );
    }

    public function writePage(Page $page, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->writePage($page, $by, $mode, $context));
    }

    public function movePage(PageRef $from, PageRef $to, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->movePage($from, $to, $by, $mode, $context));
    }

    public function deletePage(PageRef $ref, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->deletePage($ref, $by, $mode, $context));
    }

    public function writeNavigation(string $version, string $locale, NavigationTree $tree, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->writeNavigation($version, $locale, $tree, $by, $mode, $context));
    }

    public function removeNavigation(string $version, string $locale, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->removeNavigation($version, $locale, $by, $mode, $context));
    }

    public function writeManifest(Manifest $manifest, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->writeManifest($manifest, $by, $mode, $context));
    }

    public function writeAsset(string $version, string $path, string $contents): void
    {
        $this->inner->writeAsset($version, $path, $contents);
        $this->refresh();
    }

    public function deleteAsset(string $version, string $path): void
    {
        $this->inner->deleteAsset($version, $path);
        $this->refresh();
    }

    public function assets(string $version): iterable
    {
        return $this->inner->assets($version);
    }

    public function drafts(?string $version = null): iterable
    {
        return $this->inner->drafts($version);
    }

    public function publish(Revision $draft, Provenance $by): void
    {
        $this->inner->publish($draft, $by);
        $this->refresh();
    }

    public function reject(Revision $draft, Provenance $by, ?string $reason = null): void
    {
        $this->inner->reject($draft, $by, $reason);
    }

    public function revert(PageRef $page, Revision $to, Provenance $by): Revision
    {
        return $this->changed($this->inner->revert($page, $to, $by));
    }

    public function restore(Revision $to, Provenance $by, ?WriteContext $context = null): Revision
    {
        return $this->changed($this->inner->restore($to, $by, $context));
    }

    public function revision(string $id): ?Revision
    {
        return $this->inner->revision($id);
    }

    public function history(Subject $subject): iterable
    {
        return $this->inner->history($subject);
    }

    private function changed(Revision $revision): Revision
    {
        $this->refresh();

        return $revision;
    }

    /**
     * The cached value under the current fingerprint, decoded, or $resolve()'s
     * result stored there. Null results are cached too (a missing page,
     * zero-config navigation), wrapped so they are told apart from a cache miss.
     * An entry $decode cannot read (a shape this release does not write) is a miss.
     *
     * @template T
     *
     * @param  Closure(): mixed  $resolve  the plain value to cache
     * @param  Closure(mixed): T  $decode  the value as the source returns it
     * @return T
     */
    private function remember(string $name, Closure $resolve, Closure $decode): mixed
    {
        $key = CacheFormat::prefix().'source:'.$this->set.':'.$this->fingerprint().':'.hash('xxh128', $name);
        $cached = $this->cache->get($key);

        if (is_array($cached) && array_key_exists('value', $cached)) {
            try {
                return $decode($cached['value']);
            } catch (Throwable) {
                // Read it again from the source, and overwrite the entry below.
            }
        }

        $value = $resolve();
        $this->cache->forever($key, ['value' => $value]);

        return $decode($value);
    }

    /** A cached value that must be an array; anything else is an entry this release did not write. */
    private static function shape(mixed $value): array
    {
        return is_array($value) ? $value : throw new UnexpectedValueException('Unexpected cache entry.');
    }

    private function fingerprintKey(): string
    {
        return CacheFormat::prefix().'source:'.$this->set.':fingerprint';
    }
}
