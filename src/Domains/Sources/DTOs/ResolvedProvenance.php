<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;

/**
 * Who last changed each file of a repository set, and how that was found out:
 * "git" (commit history) or "mtime" (file modification times, when git is not
 * available, as on many production containers).
 */
final readonly class ResolvedProvenance
{
    /** @param array<string, Provenance> $files keyed by path relative to the set root */
    public function __construct(
        public string $method,
        public array $files,
    ) {}
}
