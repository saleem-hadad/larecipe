<?php

namespace LaRecipe\Domains\Delivery\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\Services\NavigationResolver;
use LaRecipe\Domains\Sources\Actions\GetNavigationAction;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * Page lists and navigation per set, version and locale, cached by the source's
 * fingerprint so any content change is picked up without a purge.
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
    ) {}

    public function fingerprint(DocSet $set, string $version): string
    {
        return $this->memo["fp:{$set->key}:{$version}"] ??= $this->sources->for($set->key)->fingerprint($version);
    }

    /** @return array<string, PageSummary> */
    public function summaries(DocSet $set, string $version, string $locale): array
    {
        $key = "larecipe:{$set->key}:{$version}:{$locale}:summaries:".$this->fingerprint($set, $version);

        return $this->memo[$key] ??= $this->cache->rememberForever($key, fn () => $this->summaries->execute($set->key, $version, $locale));
    }

    /**
     * Navigation as readers see it, limited to the pages they may see.
     *
     * @param  array<string, PageSummary>  $visible
     */
    public function navigation(DocSet $set, string $version, string $locale, array $visible): array
    {
        $treeKey = "larecipe:{$set->key}:{$version}:{$locale}:nav:".$this->fingerprint($set, $version);
        $tree = $this->memo[$treeKey] ??= $this->cache->rememberForever(
            $treeKey,
            fn () => $this->navigation->execute($set->key, $version, $locale, $this->summaries($set, $version, $locale)),
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
}
