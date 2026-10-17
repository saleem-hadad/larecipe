<?php

namespace LaRecipe\Domains\Sources\Services;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Contracts\ProvenanceResolver;
use LaRecipe\Domains\Sources\DTOs\ResolvedProvenance;

/**
 * The fallback when git is not available (Laravel Cloud, Vapor, containers
 * built without .git): only when a file last changed, from its modification
 * time. Better than nothing, and honest about knowing nothing more.
 */
final class MtimeProvenanceResolver implements ProvenanceResolver
{
    public function resolve(string $root, array $files, ?string $repositoryUrl = null): ResolvedProvenance
    {
        $entries = [];

        foreach ($files as $file) {
            $path = rtrim($root, '/').'/'.$file;

            if (is_file($path)) {
                $entries[$file] = new Provenance(Actor::Human, null, null, (new DateTimeImmutable)->setTimestamp((int) filemtime($path)));
            }
        }

        return new ResolvedProvenance('mtime', $entries);
    }
}
