<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A change to a set's larecipe.json, as a JSON merge patch (RFC 7386): keys in
 * the patch replace keys in the manifest, null removes a key, objects merge.
 * A whole manifest is a valid patch too, minus the keys it should remove.
 */
final readonly class ManifestDraft
{
    /** @param array<string, mixed> $patch */
    public function __construct(
        public string $set,
        public array $patch,
        public Provenance $author,
        public Channel $channel,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public ?WriteMode $mode = null,
        public ?string $migration = null,
    ) {}

    public static function fromArray(array $data, Provenance $author, Channel $channel): self
    {
        return new self(
            set: (string) $data['set'],
            patch: (array) ($data['patch'] ?? []),
            author: $author,
            channel: $channel,
            summary: $data['summary'] ?? null,
            basedOn: $data['based_on'] ?? null,
        );
    }

    public function context(?string $basedOn = null): WriteContext
    {
        return new WriteContext($this->channel, $this->summary, $basedOn, $this->migration);
    }
}
