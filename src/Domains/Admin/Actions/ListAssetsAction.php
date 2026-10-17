<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\Queries\AssetReferencesQuery;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;

/**
 * The asset manager's list for one version: every file on the set's disk,
 * its reader URL, the Markdown path to paste into a page, and the pages that
 * use it (so deleting it is a decision made with the facts).
 */
final class ListAssetsAction
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly AssetReferencesQuery $references,
        private readonly DocsUrls $urls,
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(DocSet $set, string $version): array
    {
        $source = $this->sources->for($set->key);
        $paths = [];

        if ($source instanceof WritableContentSource) {
            foreach ($source->assets($version) as $path) {
                $paths[] = (string) $path;
            }
        }

        $references = $this->references->get($set, $version, $paths);

        return array_map(function (string $path) use ($set, $version, $source, $references) {
            $asset = $source->asset($version, $path);
            $relative = preg_replace('#^_assets/#', '', $path) ?? $path;

            return [
                'path' => $path,
                'name' => basename($path),
                'mime' => $asset?->mime,
                'size' => $asset?->size,
                'hash' => $asset?->hash,
                'url' => $this->urls->assetPath($set, $version, $relative),
                'markdown' => '/_assets/'.$relative,
                'references' => $references[$path] ?? [],
            ];
        }, $paths);
    }
}
