<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A page someone wants to write, who is writing it, and where the change came from.
 *
 * basedOn is the content hash the writer read before editing. When it no longer
 * matches, the write is a conflict instead of a silent overwrite. creating says
 * the page must not exist yet (a docs migration's createPage).
 */
final readonly class PageDraft
{
    public function __construct(
        public Page $page,
        public Provenance $author,
        public Channel $channel,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public bool $creating = false,
        public ?WriteMode $mode = null,
        public ?string $migration = null,
    ) {}

    public static function fromArray(array $data, Provenance $author, Channel $channel): self
    {
        return new self(
            page: Page::fromArray($data['page']),
            author: $author,
            channel: $channel,
            summary: $data['summary'] ?? null,
            basedOn: $data['based_on'] ?? null,
            creating: (bool) ($data['creating'] ?? false),
            mode: isset($data['mode']) ? WriteMode::tryFrom((string) $data['mode']) : null,
        );
    }

    /** @param array<string, mixed> $frontmatter */
    public static function make(PageRef $ref, array $frontmatter, string $body, Provenance $author, Channel $channel, ?string $summary = null): self
    {
        return new self(Page::make($ref, new Frontmatter($frontmatter), $body), $author, $channel, $summary);
    }

    public function context(?string $basedOn = null): WriteContext
    {
        return new WriteContext($this->channel, $this->summary, $basedOn, $this->migration);
    }
}
