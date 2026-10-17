<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\DTOs\PageMove;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\Preconditions;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Moves one page to another path (or version, or locale) of the same set.
 *
 * The page is validated at its new address, the move follows the review mode,
 * and a move onto a taken path or a page that changed since the writer read it
 * is refused (or, from a docs migration, kept as a conflict draft).
 */
final class MovePageAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ReviewPolicy $review,
        private readonly ValidatePageAction $validate,
        private readonly Preconditions $preconditions,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(PageMove $move): WriteResult
    {
        $set = $move->from->set;
        $source = $this->sources->writable($set);
        $current = $source->page($move->from);

        if ($current === null) {
            $moved = $source->page($move->to);

            // Someone already made this exact move: the intent is met.
            if ($moved !== null && ($move->basedOn === null || $move->basedOn === $moved->contentHash)) {
                return WriteResult::unchanged(new ValidationReport);
            }

            return $this->refuse($move, new ValidationReport, "There is no page at [{$move->from->path}] to move.");
        }

        if ($move->from->key() === $move->to->key()) {
            return WriteResult::unchanged(new ValidationReport);
        }

        $report = $this->validate->execute(Page::make($move->to, $current->frontmatter, $current->body));

        if ($report->hasErrors()) {
            return WriteResult::rejected($report, 'The page has validation errors at its new address, so it was not moved.');
        }

        $conflict = $source->page($move->to) !== null
            ? "A page already exists at [{$move->to->path}]."
            : $this->preconditions->conflict($move->basedOn, $current->contentHash, false, "The page [{$move->from->path}]");

        if ($conflict !== null) {
            return $this->conflicted($source, $move, $report, $conflict);
        }

        $mode = $source instanceof DraftableContentSource
            ? $this->review->modeFor($set, $move->channel, $move->mode)
            : WriteMode::Publish;

        $revision = $source->movePage($move->from, $move->to, $move->author, $mode, $move->context($current->contentHash));
        $this->events->written($revision, $set);

        return $mode === WriteMode::Publish ? WriteResult::published($revision, $report) : WriteResult::drafted($revision, $report);
    }

    private function conflicted(WritableContentSource $source, PageMove $move, ValidationReport $report, string $reason): WriteResult
    {
        if ($move->channel !== Channel::Migration || ! $source instanceof DraftableContentSource) {
            return WriteResult::conflicted(null, $report, $reason);
        }

        $revision = $source->movePage($move->from, $move->to, $move->author, WriteMode::Conflict, $move->context($move->basedOn));
        $this->events->conflicted($move->from->set, $revision, (string) $move->migration, $reason);

        return WriteResult::conflicted($revision, $report, $reason.' Kept as a conflict draft for review.');
    }

    /** Nothing to move: a docs migration records a conflict and carries on, anyone else is told why. */
    private function refuse(PageMove $move, ValidationReport $report, string $reason): WriteResult
    {
        if ($move->channel !== Channel::Migration) {
            return WriteResult::rejected($report, $reason);
        }

        $this->events->conflicted($move->from->set, null, (string) $move->migration, $reason);

        return WriteResult::conflicted(null, $report, $reason);
    }
}
