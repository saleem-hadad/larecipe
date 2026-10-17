<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\Preconditions;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Deletes one page. On a database set the page stays in the revision history
 * and can be restored; on a repository set git keeps it.
 */
final class DeletePageAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ReviewPolicy $review,
        private readonly Preconditions $preconditions,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(PageDeletion $deletion): WriteResult
    {
        $set = $deletion->ref->set;
        $source = $this->sources->writable($set);
        $current = $source->page($deletion->ref);
        $report = new ValidationReport;

        if ($current === null) {
            if ($deletion->channel === Channel::Migration) {
                // Already gone in production: the intent is met, so this is not worth a conflict.
                return WriteResult::unchanged($report);
            }

            return WriteResult::rejected($report, "There is no page at [{$deletion->ref->path}] to delete.");
        }

        $conflict = $this->preconditions->conflict($deletion->basedOn, $current->contentHash, false, "The page [{$deletion->ref->path}]");

        if ($conflict !== null) {
            if ($deletion->channel !== Channel::Migration || ! $source instanceof DraftableContentSource) {
                return WriteResult::conflicted(null, $report, $conflict);
            }

            $revision = $source->deletePage($deletion->ref, $deletion->author, WriteMode::Conflict, $deletion->context($deletion->basedOn));
            $this->events->conflicted($set, $revision, (string) $deletion->migration, $conflict);

            return WriteResult::conflicted($revision, $report, $conflict.' Kept as a conflict draft for review.');
        }

        $mode = $source instanceof DraftableContentSource
            ? $this->review->modeFor($set, $deletion->channel, $deletion->mode)
            : WriteMode::Publish;

        $revision = $source->deletePage($deletion->ref, $deletion->author, $mode, $deletion->context($current->contentHash));
        $this->events->written($revision, $set);

        return $mode === WriteMode::Publish ? WriteResult::published($revision, $report) : WriteResult::drafted($revision, $report);
    }
}
