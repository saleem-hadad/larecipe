<?php

namespace LaRecipe\Domains\Sources\Laravel;

/**
 * Where the compiled provenance index of a repository set lives:
 * bootstrap/cache/larecipe/provenance-{set}.php, next to Laravel's own
 * compiled files, so `optimize:clear` style cleanups find it.
 */
final class ProvenanceIndexPath
{
    public function __construct(private readonly string $directory) {}

    public function for(string $set): string
    {
        return rtrim($this->directory, '/').'/provenance-'.$set.'.php';
    }
}
