<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Sources\DTOs\ResolvedProvenance;

/**
 * Finds who last changed each file of a repository set. The Laravel adapter
 * reads git history; without git it falls back to file modification times.
 */
interface ProvenanceResolver
{
    /** @param list<string> $files paths relative to $root */
    public function resolve(string $root, array $files): ResolvedProvenance;
}
