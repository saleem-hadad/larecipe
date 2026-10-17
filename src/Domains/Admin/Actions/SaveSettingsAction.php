<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\DTOs\SettingsChange;
use LaRecipe\Domains\Admin\Support\MergePatch;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Saves the set settings (larecipe.json) from the admin: the whole manifest
 * becomes a merge patch against production and goes through
 * UpdateManifestAction, with its schema check, review mode and basedOn guard.
 *
 * An empty patch is answered here: applying one would replace the manifest
 * with nothing.
 */
final class SaveSettingsAction
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly UpdateManifestAction $manifests,
    ) {}

    public function execute(SettingsChange $change): WriteResult
    {
        $current = $this->sources->for($change->set)->manifest()?->toArray() ?? [];
        $patch = MergePatch::between($current, $change->manifest);

        if ($patch === []) {
            return WriteResult::unchanged(new ValidationReport);
        }

        return $this->manifests->execute(new ManifestDraft(
            set: $change->set,
            patch: $patch,
            author: $change->author,
            channel: Channel::AdminUi,
            summary: $change->summary ?? 'Update the set settings',
            basedOn: $change->basedOn,
            mode: $change->mode,
        ));
    }
}
