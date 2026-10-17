<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\DTOs\ReviewDecision;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Events\DraftRejected;
use LaRecipe\Domains\Sources\Exceptions\RevisionNotFound;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Support\Contracts\Dispatcher;

/**
 * A reviewer turns a draft down, with a reason the author (often an agent)
 * can read. For a docs-migration conflict this is "keep production". Live
 * content does not change, so caches stay warm.
 */
final class RejectDraftAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly Dispatcher $events,
    ) {}

    public function execute(ReviewDecision $decision): Revision
    {
        $source = $this->sources->draftable($decision->set);
        $draft = $source->revision($decision->revisionId) ?? throw RevisionNotFound::withId($decision->set, $decision->revisionId);

        if (! $draft->isPending()) {
            throw RevisionNotFound::notPending($decision->revisionId, $draft->status);
        }

        $source->reject($draft, $decision->by, $decision->reason);
        $rejected = $source->revision($decision->revisionId) ?? $draft;

        $this->events->dispatch(new DraftRejected($rejected, $decision->by, $decision->reason));

        return $rejected;
    }
}
