<?php

namespace LaRecipe\Domains\Search\Services;

use LaRecipe\Domains\Markdown\Actions\ParsePageAction;
use LaRecipe\Domains\Search\Contracts\SearchDriver;
use LaRecipe\Domains\Search\DTOs\SearchIndex;
use LaRecipe\Domains\Search\Support\SearchCache;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * The search index of one version and locale of a set, built from its pages
 * once per content fingerprint and cached.
 *
 * Next to the full index it caches a small summary (the abilities gated
 * blocks ask about), so working out a reader's index URL on every page view
 * never loads the whole index.
 */
final class SearchIndexRepository
{
    /** Bump when extraction or indexing changes, so cached indexes are rebuilt. */
    public const VERSION = '1';

    /** @var array<string, mixed> memo for the current request */
    private array $memo = [];

    public function __construct(
        private readonly SourceResolver $sources,
        private readonly GetPageAction $pages,
        private readonly ParsePageAction $parse,
        private readonly SearchRecordExtractor $extractor,
        private readonly SearchIndexer $indexer,
        private readonly SearchCache $cache = new SearchCache,
    ) {}

    public function fingerprint(string $set, string $version, string $locale): string
    {
        return $this->memo["fingerprint|{$set}|{$version}|{$locale}"] ??= substr(hash('xxh128', implode('|', [
            self::VERSION,
            $this->sources->for($set)->fingerprint($version),
            $locale,
        ])), 0, 12);
    }

    public function get(string $set, string $version, string $locale): SearchIndex
    {
        $key = $this->key($set, $version, $locale);

        return $this->memo[$key] ??= $this->load($key) ?? $this->build($set, $version, $locale);
    }

    /** @return list<string> abilities that gated blocks in this version and locale ask about */
    public function abilities(string $set, string $version, string $locale): array
    {
        $summary = $this->cache->get($this->summaryKey($set, $version, $locale));

        return is_array($summary) ? $summary : $this->get($set, $version, $locale)->abilities;
    }

    /** Pushes the records to a custom driver once per content fingerprint. */
    public function syncTo(SearchDriver $driver, string $set, string $version, string $locale): void
    {
        $key = SearchCache::key('driver', $set, $version, $locale, $this->fingerprint($set, $version, $locale), $driver::class);

        if ($this->cache->get($key) === true) {
            return;
        }

        $driver->index($set, $version, $locale, $this->get($set, $version, $locale)->records);
        $this->cache->put($key, true);
    }

    /** Drops the cached index of the current content, for larecipe:clear. */
    public function forget(string $set, string $version, string $locale): void
    {
        $key = $this->key($set, $version, $locale);

        $this->cache->forget($key);
        $this->cache->forget($this->summaryKey($set, $version, $locale));
        unset($this->memo[$key]);
    }

    private function load(string $key): ?SearchIndex
    {
        $cached = $this->cache->get($key);

        return is_array($cached) ? SearchIndex::fromArray($cached) : null;
    }

    private function build(string $set, string $version, string $locale): SearchIndex
    {
        $source = $this->sources->for($set);
        $records = [];

        foreach ($source->pages($version, $locale) as $ref) {
            $page = $this->pages->execute($ref);

            // Hidden pages are routable but unlisted: they stay out of search too.
            if ($page === null || $page->frontmatter->hidden()) {
                continue;
            }

            // Search records every gated block with its ability, so it parses the whole page.
            $document = $this->parse->execute($page, null, ['purpose' => 'search']);
            array_push($records, ...$this->extractor->extract($page, $document));
        }

        $index = $this->indexer->build($this->fingerprint($set, $version, $locale), $records);

        $this->cache->put($this->key($set, $version, $locale), $index->toArray());
        $this->cache->put($this->summaryKey($set, $version, $locale), $index->abilities);

        return $index;
    }

    private function key(string $set, string $version, string $locale): string
    {
        return SearchCache::key('index', $set, $version, $locale, $this->fingerprint($set, $version, $locale));
    }

    private function summaryKey(string $set, string $version, string $locale): string
    {
        return SearchCache::key('abilities', $set, $version, $locale, $this->fingerprint($set, $version, $locale));
    }
}
