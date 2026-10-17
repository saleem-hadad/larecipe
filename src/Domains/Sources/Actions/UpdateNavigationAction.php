<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\Services\ZeroConfigNavigation;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Services\NavigationPatcher;
use LaRecipe\Domains\Sources\Services\Preconditions;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\Domains\Sources\Services\StructureValidator;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Writes the navigation of one version and locale, from a whole tree or a
 * patch. Patching a version without navigation.json starts from the derived
 * (zero-config) tree, so the first patch freezes what readers already see.
 */
final class UpdateNavigationAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ReviewPolicy $review,
        private readonly ListPageSummariesAction $summaries,
        private readonly ZeroConfigNavigation $zeroConfig,
        private readonly NavigationPatcher $patcher,
        private readonly StructureValidator $validator,
        private readonly Preconditions $preconditions,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(NavigationDraft $draft): WriteResult
    {
        $source = $this->sources->writable($draft->set);
        $current = $source->navigation($draft->version, $draft->locale);
        $currentHash = ContentHashes::navigation($current);
        $summaries = $this->summaries->execute($draft->set, $draft->version, $draft->locale);

        try {
            $tree = $draft->tree ?? $this->patcher->apply($current ?? $this->zeroConfig->build($summaries), $draft->patch ?? []);
        } catch (InvalidContentChange $exception) {
            $issue = new ValidationIssue(ValidationIssue::ERROR, 'navigation.patch.invalid', $exception->getMessage(), "navigation {$draft->version}/{$draft->locale}");

            return WriteResult::rejected(new ValidationReport([$issue]), $exception->getMessage());
        }

        $report = $this->validator->navigation($tree, array_keys($summaries), "navigation {$draft->version}/{$draft->locale}");

        if ($current !== null && ContentHashes::navigation($tree) === $currentHash) {
            return WriteResult::unchanged($report);
        }

        $conflict = $this->preconditions->conflict($draft->basedOn, $currentHash, false, "The navigation of [{$draft->version}/{$draft->locale}]");

        if ($conflict !== null) {
            return $this->conflicted($source, $draft, $tree, $report, $conflict);
        }

        $mode = $source instanceof DraftableContentSource
            ? $this->review->modeFor($draft->set, $draft->channel, $draft->mode)
            : WriteMode::Publish;

        $revision = $source->writeNavigation($draft->version, $draft->locale, $tree, $draft->author, $mode, $draft->context($currentHash));
        $this->events->written($revision, $draft->set);

        return $mode === WriteMode::Publish ? WriteResult::published($revision, $report) : WriteResult::drafted($revision, $report);
    }

    private function conflicted(WritableContentSource $source, NavigationDraft $draft, NavigationTree $tree, ValidationReport $report, string $reason): WriteResult
    {
        if ($draft->channel !== Channel::Migration || ! $source instanceof DraftableContentSource) {
            return WriteResult::conflicted(null, $report, $reason.' Read it again and retry.');
        }

        $revision = $source->writeNavigation($draft->version, $draft->locale, $tree, $draft->author, WriteMode::Conflict, $draft->context($draft->basedOn));
        $this->events->conflicted($draft->set, $revision, (string) $draft->migration, $reason);

        return WriteResult::conflicted($revision, $report, $reason.' Kept as a conflict draft for review.');
    }
}
