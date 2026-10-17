<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\Channel;

/**
 * Bring a page (or the navigation, or the manifest) back to an earlier
 * revision. The restore is itself a new revision, so a revert can be reverted.
 */
final readonly class RevertRequest
{
    public function __construct(
        public string $set,
        public string $revisionId,
        public Provenance $by,
        public Channel $channel = Channel::AdminUi,
        public ?string $summary = null,
    ) {}

    public static function fromArray(array $data, Provenance $by, Channel $channel = Channel::AdminUi): self
    {
        return new self((string) $data['set'], (string) $data['revision'], $by, $channel, $data['summary'] ?? null);
    }
}
