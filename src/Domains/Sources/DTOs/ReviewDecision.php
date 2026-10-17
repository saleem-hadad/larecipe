<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;

/**
 * A reviewer's decision on one draft or conflict: publish it, or reject it with a reason.
 */
final readonly class ReviewDecision
{
    public function __construct(
        public string $set,
        public string $revisionId,
        public Provenance $by,
        public ?string $reason = null,
    ) {}

    public static function fromArray(array $data, Provenance $by): self
    {
        return new self((string) $data['set'], (string) $data['revision'], $by, $data['reason'] ?? null);
    }
}
