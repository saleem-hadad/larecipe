<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\Asset;
use LaRecipe\Domains\Sources\DTOs\AssetUpload;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * Stores an asset (an upload in the admin UI, a source move). Repository sets
 * write it into the docs folder; database sets put it on their filesystem disk.
 * Assets are not revisioned: pages that reference them are.
 */
final class WriteAssetAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(AssetUpload $upload): ?Asset
    {
        $source = $this->sources->writable($upload->set);
        $source->writeAsset($upload->version, $upload->path, $upload->contents);
        $this->events->changed($upload->set);

        return $source->asset($upload->version, $upload->path);
    }
}
