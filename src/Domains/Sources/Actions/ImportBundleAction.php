<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sources\DTOs\Bundle;
use LaRecipe\Domains\Sources\DTOs\MoveReport;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Services\BundleArchive;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\SourceComparer;
use LaRecipe\Domains\Sources\Services\SourceCopier;
use LaRecipe\Domains\Sources\Services\SourceInventory;
use LaRecipe\Domains\Sources\Services\WritableSources;
use RuntimeException;

/**
 * Loads a bundle (a directory or a .tar.gz from larecipe:source:export) into a
 * set's configured source: validated first, written as Actor::Migration, and
 * optionally proven by re-reading and comparing content hashes.
 */
final class ImportBundleAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly SourceCopier $copier,
        private readonly SourceComparer $comparer,
        private readonly SourceInventory $inventory,
        private readonly BundleArchive $archive,
        private readonly FrontmatterParser $parser,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(string $set, string $path, bool $verify = false, ?string $by = null): MoveReport
    {
        $archive = Bundle::isArchive($path);
        $bundle = $archive ? Bundle::temporary() : new Bundle($path);

        if (! $archive && ! is_dir($path)) {
            throw new RuntimeException("There is no bundle at [{$path}].");
        }

        try {
            if ($archive) {
                $this->archive->unpack($path, $bundle->path);
            }

            $snapshot = new RepositorySource($set, $bundle->path, $this->parser);
            $target = $this->sources->writable($set);
            $plan = $this->inventory->plan($set, $snapshot);
            $validation = $this->inventory->validate($snapshot);

            if ($validation->hasErrors()) {
                return new MoveReport($plan, $validation, false);
            }

            $this->copier->copy($snapshot, $target, new Provenance(Actor::Migration, $by ?? 'larecipe:source:import', basename($path)), WriteContext::migration('source-import', 'Import '.basename($path)));
            $this->events->changed($set);

            return $verify
                ? new MoveReport($plan, $validation, true, true, $this->comparer->compare($snapshot, $target))
                : new MoveReport($plan, $validation, true);
        } finally {
            if ($archive) {
                $this->archive->remove($bundle->path);
            }
        }
    }
}
