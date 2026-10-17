<?php

namespace LaRecipe\Domains\Sources\Events;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * A reviewer rejected a draft (or kept production's side of a conflict).
 */
final readonly class DraftRejected
{
    public function __construct(
        public Revision $revision,
        public Provenance $by,
        public ?string $reason = null,
    ) {}
}
