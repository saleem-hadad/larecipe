<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\DTOs\ReviewDecision;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Sources\Events\DraftPublished;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Exceptions\RevisionNotFound;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;
use LaRecipe\Support\Contracts\Dispatcher;

/**
 * A reviewer publishes a draft from the review queue: an agent's change made
 * outside a pull request, a person's saved draft, or a docs migration's side
 * of a conflict ("take the migration"). The page is validated again first,
 * because rules may have changed since the draft was written.
 */
final class PublishDraftAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ValidatePageAction $validate,
        private readonly ChangeEvents $changes,
        private readonly Dispatcher $events,
    ) {}

    public function execute(ReviewDecision $decision): WriteResult
    {
        $source = $this->sources->draftable($decision->set);
        $draft = $source->revision($decision->revisionId) ?? throw RevisionNotFound::withId($decision->set, $decision->revisionId);

        if (! $draft->isPending()) {
            throw RevisionNotFound::notPending($decision->revisionId, $draft->status);
        }

        $report = new ValidationReport;

        if ($draft->type() === SubjectType::Page && $draft->operation !== RevisionOperation::Delete->value && $draft->ref !== null) {
            $report = $this->validate->execute(Page::make($draft->ref, new Frontmatter((array) ($draft->payload['frontmatter'] ?? [])), (string) ($draft->payload['body'] ?? '')));

            if ($report->hasErrors()) {
                return WriteResult::rejected($report, 'The draft has validation errors, so it was not published. Edit it first.');
            }
        }

        try {
            $source->publish($draft, $decision->by);
        } catch (InvalidContentChange $exception) {
            // A move whose new path got taken meanwhile: tell the reviewer instead of failing.
            return WriteResult::rejected($report, $exception->getMessage());
        }

        $published = $source->revision($decision->revisionId) ?? $draft;

        $this->events->dispatch(new DraftPublished($published, $decision->by));
        $this->changes->written($published, $decision->set);

        return WriteResult::published($published, $report);
    }
}
