<?php

namespace LaRecipe\Domains\Sources\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Live content of a set changed (a publish, a revert, a docs migration, a
 * source move). Caches keyed by the set's fingerprint are already stale; this
 * event lets listeners warm the new ones (pages, indexes, llms.txt).
 */
final readonly class ContentChanged
{
    /** @param list<PageRef> $pages the pages that changed, when known */
    public function __construct(
        public string $set,
        public array $pages = [],
    ) {}
}
