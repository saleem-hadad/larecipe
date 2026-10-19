<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A request to delete one page.
 */
final readonly class PageDeletion
{
    public function __construct(
        public PageRef $ref,
        public Provenance $author,
        public Channel $channel,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public ?WriteMode $mode = null,
        public ?string $migration = null,
    ) {}

    public static function fromArray(array $data, Provenance $author, Channel $channel): self
    {
        $ref = $data['ref'];

        return new self(
            ref: new PageRef($ref['set'], $ref['version'], $ref['locale'], PageRef::normalizePath($ref['path'])),
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
