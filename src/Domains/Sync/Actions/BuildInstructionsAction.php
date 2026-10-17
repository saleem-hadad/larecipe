<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Services\InstructionsRenderer;
use LaRecipe\Domains\Sync\Services\PushRange;

/**
 * The text of LaRecipe's section for AI coding sessions: the before-you-push
 * instructions (ARCHITECTURE.md 14.2), then, when enabled, the docs index.
 *
 * Built from facts every time (the driver, the docs path, the default branch
 * from origin/HEAD, the review mode), never hand-written, so it cannot fall
 * behind the app's config. The AGENTS.md block and the Boost guideline both
 * use it, so the two can never disagree.
 */
final class BuildInstructionsAction
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly InstructionsRenderer $instructions,
        private readonly BuildDocsIndexAction $index,
        private readonly PushRange $range,
        private readonly SyncSettings $settings,
    ) {}

    public function execute(?bool $withIndex = null, ?string $indexSet = null): string
    {
        $sets = $this->orderedSets();
        $text = $this->instructions->render($sets, $this->range->defaultBranch());

        if (($withIndex ?? $this->settings->index) && $sets !== []) {
            $index = $this->index->execute($indexSet ?? $sets[0]->key);

            if ($index !== null) {
                $text .= "\n\n".$index;
            }
        }

        return $text;
    }

    /** @return list<CatalogSet> the default set first, the rest in config order */
    private function orderedSets(): array
    {
        $sets = $this->catalog->sets();

        usort($sets, fn (CatalogSet $a, CatalogSet $b) => ($b->key === $this->settings->defaultSet) <=> ($a->key === $this->settings->defaultSet));

        return $sets;
    }
}
