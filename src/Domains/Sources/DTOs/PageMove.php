<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A request to move a page to another path (or version, or locale) in the same set.
 */
final readonly class PageMove
{
    public function __construct(
        public PageRef $from,
        public PageRef $to,
        public Provenance $author,
        public Channel $channel,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public ?WriteMode $mode = null,
        public ?string $migration = null,
    ) {}

    public static function fromArray(array $data, Provenance $author, Channel $channel): self
    {
        $from = $data['from'];
        $to = $data['to'];

        return new self(
            from: new PageRef($from['set'], $from['version'], $from['locale'], PageRef::normalizePath($from['path'])),
            to: new PageRef($to['set'] ?? $from['set'], $to['version'] ?? $from['version'], $to['locale'] ?? $from['locale'], PageRef::normalizePath($to['path'])),
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
