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
use LaRecipe\Domains\Sources\Services\SourceCopier;
use LaRecipe\Domains\Sources\Services\SourceInventory;
use LaRecipe\Domains\Sources\Services\WritableSources;
use RuntimeException;

/**
 * Writes a set out as a bundle of canonical files, to a directory or a
 * .tar.gz: a backup, a hand-off, the way to bring admin UI edits back into
 * git, or what `larecipe:pull` reads to refresh a local database set.
 */
final class ExportSourceAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly SourceCopier $copier,
        private readonly SourceInventory $inventory,
        private readonly BundleArchive $archive,
        private readonly FrontmatterParser $parser,
    ) {}

    public function execute(string $set, string $destination): MoveReport
    {
        $source = $this->sources->writable($set);
        $archive = Bundle::isArchive($destination);
        $bundle = $archive ? Bundle::temporary() : new Bundle($destination);

        if (! $archive && is_dir($destination) && (new \FilesystemIterator($destination))->valid()) {
            throw new RuntimeException("[{$destination}] is not empty. Export into a new or empty directory, or to a .tar.gz file.");
        }

        try {
            $snapshot = new RepositorySource($set, $bundle->path, $this->parser);
            $this->copier->copy($source, $snapshot, new Provenance(Actor::Migration, 'larecipe:source:export'), WriteContext::migration('source-export'));

            if ($archive) {
                $this->archive->pack($bundle->path, $destination);
            }

            return new MoveReport($this->inventory->plan($set, $snapshot), $this->inventory->validate($snapshot), true, destination: $destination);
        } finally {
            if ($archive) {
                $this->archive->remove($bundle->path);
            }
        }
    }
}
