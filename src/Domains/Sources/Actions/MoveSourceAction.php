<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Sources\Contracts\SourceFactory;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sources\DTOs\Bundle;
use LaRecipe\Domains\Sources\DTOs\MoveReport;
use LaRecipe\Domains\Sources\DTOs\SourceMoveRequest;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Services\BundleArchive;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\SetLayout;
use LaRecipe\Domains\Sources\Services\SourceComparer;
use LaRecipe\Domains\Sources\Services\SourceCopier;
use LaRecipe\Domains\Sources\Services\SourceInventory;

/**
 * Moves a set between the repository and the database, losslessly
 * (ARCHITECTURE.md 5.6): snapshot the source into a bundle of canonical files,
 * validate it, write it to the target as Actor::Migration, then (with verify)
 * re-read the target and compare normalized content hashes for every page,
 * navigation, manifest and asset.
 *
 * Revision history does not move: git history and revision tables are not
 * equivalent, and the report says so. The config change that switches
 * serving to the new driver is the caller's to make; this never edits config.
 */
final class MoveSourceAction
{
    public function __construct(
        private readonly SourceFactory $sources,
        private readonly SourceCopier $copier,
        private readonly SourceComparer $comparer,
        private readonly SourceInventory $inventory,
        private readonly BundleArchive $archive,
        private readonly FrontmatterParser $parser,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(SourceMoveRequest $request): MoveReport
    {
        $from = $this->sources->make($request->set, $request->from, $request->fromOptions);
        $to = $this->sources->make($request->set, $request->to, $request->toOptions);
        $bundle = Bundle::temporary();
        $snapshot = new RepositorySource($request->set, $bundle->path, $this->parser);
        $by = new Provenance(Actor::Migration, 'larecipe:source:move', "{$request->from} to {$request->to}");
        $context = WriteContext::migration('source-move', "Move the {$request->set} set from the {$request->from} source to the {$request->to} source");

        try {
            $this->copier->copy($from, $snapshot, $by, $context);

            $plan = $this->inventory->plan($request->set, $snapshot)->withWarnings($this->warnings($snapshot, $to));
            $validation = $this->inventory->validate($snapshot);

            if ($request->dryRun || $validation->hasErrors()) {
                return new MoveReport($plan, $validation, false);
            }

            $this->copier->copy($snapshot, $to, $by, $context);
            $this->events->changed($request->set);

            return $request->verify
                ? new MoveReport($plan, $validation, true, true, $this->comparer->compare($snapshot, $to))
                : new MoveReport($plan, $validation, true);
        } finally {
            $this->archive->remove($bundle->path);
        }
    }

    /** @return list<string> */
    private function warnings(RepositorySource $snapshot, WritableContentSource $target): array
    {
        $warnings = ['Revision history is not moved: git history and revision tables are not equivalent. The target starts its own history from this move.'];
        $manifest = $snapshot->manifest();
        $extra = 0;

        foreach (SetLayout::versions($manifest) as $version) {
            foreach (SetLayout::locales($manifest) as $locale) {
                foreach ($target->pages($version, $locale) as $ref) {
                    $extra += $snapshot->page($ref) === null ? 1 : 0;
                }
            }
        }

        if ($extra > 0) {
            $warnings[] = "The target already holds {$extra} page(s) the source does not have. They are kept.";
        }

        return $warnings;
    }
}
