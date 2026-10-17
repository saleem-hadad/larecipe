<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Services\Preconditions;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Writes one page on behalf of a person or an agent.
 *
 * Every writer goes through this action (MCP tools, the admin UI, larecipe:sync,
 * docs migrations, onboarding), so validation, the review mode and provenance are
 * applied the same way no matter who is writing.
 *
 * A write against a stale basedOn hash never overwrites: a docs migration keeps
 * its version as a conflict draft next to production's, any other writer is
 * told to read the page again.
 */
final class WritePageAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ReviewPolicy $review,
        private readonly ValidatePageAction $validate,
        private readonly Preconditions $preconditions,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(PageDraft $draft): WriteResult
    {
        $page = $draft->page;
        $set = $page->ref->set;
        $report = $this->validate->execute($page);

        if ($report->hasErrors()) {
            return WriteResult::rejected($report, 'The page has validation errors, so nothing was written.');
        }

        $source = $this->sources->writable($set);
        $current = $source->page($page->ref);
        $currentHash = ContentHashes::page($current);

        // Already exactly what the writer wants (someone made the same edit): nothing to do, nothing to conflict with.
        if ($current !== null && $current->contentHash === $page->contentHash) {
            return WriteResult::unchanged($report);
        }

        $conflict = $this->preconditions->conflict($draft->basedOn, $currentHash, $draft->creating, "The page [{$page->ref->path}]");

        if ($conflict !== null) {
            return $this->conflicted($source, $draft, $report, $conflict);
        }

        $mode = $source instanceof DraftableContentSource
            ? $this->review->modeFor($set, $draft->channel, $draft->mode)
            : WriteMode::Publish;

        $revision = $source->writePage($page, $draft->author, $mode, $draft->context($currentHash));
        $this->events->written($revision, $set);

        return $mode === WriteMode::Publish ? WriteResult::published($revision, $report) : WriteResult::drafted($revision, $report);
    }

    private function conflicted(WritableContentSource $source, PageDraft $draft, ValidationReport $report, string $reason): WriteResult
    {
        if ($draft->channel !== Channel::Migration || ! $source instanceof DraftableContentSource) {
            return WriteResult::conflicted(null, $report, $reason.' Read it again and retry.');
        }

        $basedOn = $draft->basedOn ?? ($draft->creating ? ContentHashes::ABSENT : null);
        $revision = $source->writePage($draft->page, $draft->author, WriteMode::Conflict, $draft->context($basedOn));
        $this->events->conflicted($draft->page->ref->set, $revision, (string) $draft->migration, $reason);

        return WriteResult::conflicted($revision, $report, $reason.' Kept as a conflict draft for review.');
    }
}
