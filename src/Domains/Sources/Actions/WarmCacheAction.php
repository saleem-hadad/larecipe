<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\CachedSource;
use LaRecipe\Domains\Sources\DTOs\CacheWarmReport;
use LaRecipe\Domains\Sources\Services\SetLayout;

/**
 * Fills a database set's cache for its current fingerprint: the manifest, and
 * for every version and locale the page list, each page and the navigation.
 *
 * The fingerprint is read from the database first, so running this after
 * `php artisan migrate` in a deploy script picks up what the docs migrations
 * just published. Repository sets have nothing to warm here.
 */
final class WarmCacheAction
{
    public function __construct(private readonly SourceResolver $sources) {}

    public function execute(string $set): CacheWarmReport
    {
        $source = $this->sources->for($set);

        if (! $source instanceof CachedSource) {
            return CacheWarmReport::skipped($set);
        }

        $fingerprint = $source->refresh();
        $manifest = $source->manifest();
        $pages = 0;

        foreach (SetLayout::versions($manifest) as $version) {
            foreach (SetLayout::locales($manifest) as $locale) {
                foreach ($source->pages($version, $locale) as $ref) {
                    $source->page($ref);
                    $pages++;
                }

                $source->navigation($version, $locale);
            }
        }

        return new CacheWarmReport($set, true, $pages, $fingerprint);
    }
}
