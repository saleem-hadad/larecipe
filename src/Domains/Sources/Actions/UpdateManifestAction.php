<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Services\JsonMergePatch;
use LaRecipe\Domains\Sources\Services\Preconditions;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;
use LaRecipe\Domains\Sources\Services\StructureValidator;
use LaRecipe\Domains\Sources\Services\WritableSources;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Changes a set's larecipe.json with a JSON merge patch. Presentation and
 * content structure only: infrastructure stays in config/larecipe.php
 * (the Two-Files Rule), and secrets never belong in either JSON file.
 *
 * A database set's manifest may not declare integrations: analytics scripts
 * run with every reader's session, so they come from config/larecipe.php
 * (SetConfig::allowsManifestIntegrations()). Removing old ones is fine.
 */
final class UpdateManifestAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly ReviewPolicy $review,
        private readonly JsonMergePatch $patch,
        private readonly StructureValidator $validator,
        private readonly Preconditions $preconditions,
        private readonly ChangeEvents $events,
        private readonly SetRegistry $sets,
    ) {}

    public function execute(ManifestDraft $draft): WriteResult
    {
        if (($draft->patch['integrations'] ?? null) !== null && ! $this->sets->config($draft->set)->allowsManifestIntegrations()) {
            return $this->refuseIntegrations($draft->set);
        }

        $source = $this->sources->writable($draft->set);
        $current = $source->manifest();
        $currentHash = ContentHashes::manifest($current);
        $data = $this->patch->apply($current?->toArray() ?? [], $draft->patch);
        $manifest = Manifest::fromArray(is_array($data) ? $data : [], ucfirst($draft->set));
        $report = $this->validator->manifest($manifest);

        if ($report->hasErrors()) {
            return WriteResult::rejected($report, 'The manifest has validation errors, so it was not written.');
        }

        if ($current !== null && ContentHashes::manifest($manifest) === $currentHash) {
            return WriteResult::unchanged($report);
        }

        $conflict = $this->preconditions->conflict($draft->basedOn, $currentHash, false, 'larecipe.json');

        if ($conflict !== null) {
            if ($draft->channel !== Channel::Migration || ! $source instanceof DraftableContentSource) {
                return WriteResult::conflicted(null, $report, $conflict.' Read it again and retry.');
            }

            $revision = $source->writeManifest($manifest, $draft->author, WriteMode::Conflict, $draft->context($draft->basedOn));
            $this->events->conflicted($draft->set, $revision, (string) $draft->migration, $conflict);

            return WriteResult::conflicted($revision, $report, $conflict.' Kept as a conflict draft for review.');
        }

        $mode = $source instanceof DraftableContentSource
            ? $this->review->modeFor($draft->set, $draft->channel, $draft->mode)
            : WriteMode::Publish;

        $revision = $source->writeManifest($manifest, $draft->author, $mode, $draft->context($currentHash));
        $this->events->written($revision, $draft->set);

        return $mode === WriteMode::Publish ? WriteResult::published($revision, $report) : WriteResult::drafted($revision, $report);
    }

    private function refuseIntegrations(string $set): WriteResult
    {
        $issue = ValidationIssue::error(
            'manifest.integrations',
            "A database set takes its analytics from config/larecipe.php (sets.{$set}.integrations), not from larecipe.json.",
            fix: "Remove integrations from the change and add them to sets.{$set}.integrations in config/larecipe.php.",
            file: 'larecipe.json',
        );

        return WriteResult::rejected(new ValidationReport([$issue]), $issue->message.' Nothing was written.');
    }
}
