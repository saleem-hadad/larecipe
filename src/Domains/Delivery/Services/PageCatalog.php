<?php

namespace LaRecipe\Domains\Delivery\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\Services\NavigationResolver;
use LaRecipe\Domains\Sources\Actions\GetNavigationAction;
use LaRecipe\Domains\Sources\Actions\ListLocalizedPagesAction;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Support\CacheFormat;
use Throwable;

/**
 * Page lists and navigation per set, version and locale, cached by the source's
 * fingerprint so any content change is picked up without a purge.
 *
 * Only plain arrays cross the cache: Laravel 13 stores unserialize no classes
 * unless the host allows them (cache.serializable_classes).
 */
final class PageCatalog
{
    /** @var array<string, array> memo for the current request */
    private array $memo = [];

    public function __construct(
        private readonly Cache $cache,
        private readonly SourceResolver $sources,
        private readonly ListPageSummariesAction $summaries,
        private readonly GetNavigationAction $navigation,
        private readonly NavigationResolver $resolver,
        private readonly ListLocalizedPagesAction $localized,
    ) {}

    public function fingerprint(DocSet $set, string $version): string
    {
        return $this->memo["fp:{$set->key}:{$version}"] ??= $this->sources->for($set->key)->fingerprint($version);
    }

    /** @return array<string, PageSummary> */
    public function summaries(DocSet $set, string $version, string $locale): array
    {
        $key = CacheFormat::prefix()."{$set->key}:{$version}:{$locale}:summaries:".$this->fingerprint($set, $version);

        return $this->memo[$key] ??= $this->rememberSummaries($key, fn () => $this->summaries->execute($set->key, $version, $locale));
    }

    /**
     * The pages a locale serves: every default-locale page, translated where a
     * translation exists. A summary whose ref keeps the default locale is a fallback.
     *
     * @return array<string, PageSummary>
     */
    public function localized(DocSet $set, string $version, string $locale): array
    {
        if ($locale === $set->defaultLocale()->code) {
            return $this->summaries($set, $version, $locale);
        }

        $key = CacheFormat::prefix()."{$set->key}:{$version}:{$locale}:localized:".$this->fingerprint($set, $version);

        return $this->memo[$key] ??= $this->rememberSummaries($key, fn () => $this->localized->execute($set, $version, $locale));
    }

    /**
     * Navigation as readers see it, limited to the pages they may see.
     *
     * @param  array<string, PageSummary>  $visible
     */
    public function navigation(DocSet $set, string $version, string $locale, array $visible): array
    {
        $treeKey = CacheFormat::prefix()."{$set->key}:{$version}:{$locale}:nav:".$this->fingerprint($set, $version);
        $tree = $this->memo[$treeKey] ??= $this->rememberTree(
            $treeKey,
            fn () => $this->navigation->execute($set->key, $version, $locale, $this->localized($set, $version, $locale)),
        );

        return $this->resolver->resolve($tree, $visible);
    }

    public function sequence(array $tabs): array
    {
        return $this->resolver->sequence($tabs);
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    /**
     * @param  Closure(): array<string, PageSummary>  $resolve
     * @return array<string, PageSummary>
     */
    private function rememberSummaries(string $key, Closure $resolve): array
    {
        $cached = $this->cache->get($key);

        try {
            if (is_array($cached)) {
                return array_map(fn (array $summary) => PageSummary::fromCache($summary), $cached);
            }
        } catch (Throwable) {
            // An entry in a shape this release does not write: a miss.
        }

        $summaries = $resolve();
        $this->cache->forever($key, array_map(fn (PageSummary $summary) => $summary->toCache(), $summaries));

        return $summaries;
    }

    /** @param  Closure(): NavigationTree  $resolve */
    private function rememberTree(string $key, Closure $resolve): NavigationTree
    {
        $cached = $this->cache->get($key);

        try {
            if (is_array($cached) && is_array($cached['tree'] ?? null)) {
                $tree = NavigationTree::fromJson($cached['tree']);

                return new NavigationTree($tree->tabs, (bool) ($cached['derived'] ?? false));
            }
        } catch (Throwable) {
            // An entry in a shape this release does not write: a miss.
        }

        $tree = $resolve();
        $this->cache->forever($key, ['tree' => $tree->toJson(), 'derived' => $tree->derived]);

        return $tree;
    }
}
