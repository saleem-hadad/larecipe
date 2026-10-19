<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * Removes one asset. The admin UI checks that no page references it first.
 */
final class DeleteAssetAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(string $set, string $version, string $path): void
    {
        $this->sources->writable($set)->deleteAsset($version, $path);
        $this->events->changed($set);
    }
}
