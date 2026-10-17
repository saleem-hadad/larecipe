<?php

namespace LaRecipe\Domains\Admin\Queries;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * Which pages (and whether the manifest) use an asset, so the asset manager
 * never deletes an image a page still shows.
 *
 * Authors reference an asset several ways ("/_assets/flow.png",
 * "../_assets/flow.png", "_assets/flow.png", or "flow.png" next to the page),
 * so a page counts as a reference when its source names the asset's path
 * inside _assets/ as a path segment. A false positive only means a person
 * confirms a deletion that was safe; a false negative would break a page.
 */
final class AssetReferencesQuery
{
    public function __construct(
        private readonly ListPageSummariesAction $summaries,
        private readonly GetPageAction $pages,
        private readonly SourceResolver $sources,
    ) {}

    /**
     * @param  list<string>  $assets  paths relative to the version root ("_assets/flow.png")
     * @return array<string, list<string>> asset path => the pages and files that use it
     */
    public function get(DocSet $set, string $version, array $assets): array
    {
        $references = array_fill_keys($assets, []);

        if ($assets === []) {
            return $references;
        }

        $manifest = json_encode($this->sources->for($set->key)->manifest()?->toArray() ?? [], JSON_UNESCAPED_SLASHES) ?: '';

        foreach ($assets as $asset) {
            if ($this->mentions($manifest, $asset)) {
                $references[$asset][] = 'larecipe.json';
            }
        }

        foreach ($set->locales() as $locale) {
            foreach ($this->summaries->execute($set->key, $version, $locale->code) as $summary) {
                $page = $this->pages->execute($summary->ref);
                $text = $page === null ? '' : json_encode($page->frontmatter->toArray(), JSON_UNESCAPED_SLASHES)."\n".$page->body;

                foreach ($assets as $asset) {
                    if ($this->mentions((string) $text, $asset)) {
                        $references[$asset][] = $locale->default ? $summary->ref->path : "{$locale->code}/{$summary->ref->path}";
                    }
                }
            }
        }

        return array_map(fn (array $pages) => array_values(array_unique($pages)), $references);
    }

    private function mentions(string $text, string $asset): bool
    {
        $relative = preg_replace('#^_assets/#', '', ltrim($asset, '/')) ?? $asset;

        return str_contains($text, '_assets/'.$relative)
            || preg_match('#(^|[\s("\'/=])'.preg_quote($relative, '#').'($|[\s)"\'?\#])#m', $text) === 1;
    }
}
