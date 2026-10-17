<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * New navigation for one version and locale: either a whole tree, or a patch
 * applied to the current tree (the derived one when the version has no
 * navigation.json yet). See NavigationPatcher for the patch format.
 */
final readonly class NavigationDraft
{
    /** @param list<array<string, mixed>>|null $patch */
    public function __construct(
        public string $set,
        public string $version,
        public string $locale,
        public Provenance $author,
        public Channel $channel,
        public ?NavigationTree $tree = null,
        public ?array $patch = null,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public ?WriteMode $mode = null,
        public ?string $migration = null,
    ) {}

    public static function fromArray(array $data, Provenance $author, Channel $channel): self
    {
        return new self(
            set: (string) $data['set'],
            version: (string) $data['version'],
            locale: (string) $data['locale'],
            author: $author,
            channel: $channel,
            tree: isset($data['tree']) && is_array($data['tree']) ? NavigationTree::fromJson($data['tree']) : null,
            patch: isset($data['patch']) && is_array($data['patch']) ? array_values($data['patch']) : null,
            summary: $data['summary'] ?? null,
            basedOn: $data['based_on'] ?? null,
        );
    }

    public function context(?string $basedOn = null): WriteContext
    {
        return new WriteContext($this->channel, $this->summary, $basedOn, $this->migration);
    }
}
