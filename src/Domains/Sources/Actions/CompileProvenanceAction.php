<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Sources\Contracts\ProvenanceResolver;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sources\DTOs\ProvenanceReport;
use LaRecipe\Domains\Sources\Services\ProvenanceIndexWriter;
use LaRecipe\Support\Contracts\Clock;

/**
 * Compiles who last changed each page of a repository set into an index the
 * set reads at request time ("Updated 3 days ago by Claude, PR #412").
 *
 * Runs at build time: larecipe:optimize calls it, and so does
 * larecipe:provenance. Git history when git is available, file modification
 * times otherwise. Database sets skip it: their revisions already carry
 * provenance.
 */
final class CompileProvenanceAction
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly ProvenanceResolver $resolver,
        private readonly ProvenanceIndexWriter $writer,
        private readonly Clock $clock,
    ) {}

    public function execute(string $set): ProvenanceReport
    {
        $source = $this->sources->for($set);

        if (! $source instanceof RepositorySource || $source->provenanceIndexPath() === null) {
            return ProvenanceReport::skipped($set, 'Database sets record provenance in their revisions.');
        }

        $resolved = $this->resolver->resolve($source->root(), $source->contentFiles(), $source->manifest()?->repository()['url']);
        $path = $source->provenanceIndexPath();

        $this->writer->write($path, $resolved, $this->clock->now());
        $source->forgetProvenance();

        return new ProvenanceReport($set, true, count($resolved->files), $resolved->method, $path);
    }
}
