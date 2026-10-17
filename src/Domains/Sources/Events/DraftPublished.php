<?php

namespace LaRecipe\Domains\Sources\Events;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * A reviewer published a draft (or took a docs migration's side of a conflict).
 */
final readonly class DraftPublished
{
    public function __construct(
        public Revision $revision,
        public Provenance $by,
    ) {}
}
