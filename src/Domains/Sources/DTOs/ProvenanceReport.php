<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * What CompileProvenanceAction did for one set (larecipe:optimize and
 * larecipe:provenance print it).
 */
final readonly class ProvenanceReport
{
    public function __construct(
        public string $set,
        public bool $compiled,
        public int $files = 0,
        public ?string $method = null,
        public ?string $path = null,
        public ?string $reason = null,
    ) {}

    public static function skipped(string $set, string $reason): self
    {
        return new self($set, false, reason: $reason);
    }
}
