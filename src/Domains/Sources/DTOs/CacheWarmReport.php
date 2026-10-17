<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * What larecipe:cache:warm (or the queued warm listener) did for one set.
 */
final readonly class CacheWarmReport
{
    public function __construct(
        public string $set,
        public bool $cached,
        public int $pages = 0,
        public ?string $fingerprint = null,
    ) {}

    public static function skipped(string $set): self
    {
        return new self($set, false);
    }
}
