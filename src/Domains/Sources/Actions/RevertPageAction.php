<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\RevertRequest;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Exceptions\RevisionNotFound;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * One step back: restores a page (or the navigation, or the manifest) to an
 * earlier revision, as a new published revision. The revision it replaces is
 * marked reverted, so the history shows exactly what happened and the revert
 * itself can be reverted.
 */
final class RevertPageAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(RevertRequest $request): WriteResult
    {
        $source = $this->sources->draftable($request->set);
        $target = $source->revision($request->revisionId) ?? throw RevisionNotFound::withId($request->set, $request->revisionId);

        $revision = $source->restore($target, $request->by, new WriteContext(
            $request->channel,
            $request->summary ?? "Revert to revision #{$target->id}",
        ));

        $this->events->written($revision, $request->set);

        return WriteResult::published($revision, new ValidationReport);
    }
}
