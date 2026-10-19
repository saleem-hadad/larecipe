<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType;

/**
 * One declarative step of a docs migration, as written in the file:
 * createPage, updatePage, movePage, deletePage, updateNavigation or
 * updateManifest. Pages are addressed the way people write them
 * ("3.x/billing/exports"); navigation by version (and locale).
 *
 * basedOn is the content hash the change was written against
 * (ContentHashes::ABSENT when the subject did not exist); null skips the check.
 */
final readonly class DocsMigrationOperation
{
    /**
     * @param  array<string, mixed>  $frontmatter
     * @param  array<string, mixed>|null  $tree  a navigation.json tree
     * @param  array<int|string, mixed>|null  $patch  a navigation patch (list) or a manifest merge patch (object)
     */
    public function __construct(
        public DocsMigrationOperationType $type,
        public string $target = '',
        public ?string $basedOn = null,
        public array $frontmatter = [],
        public ?string $body = null,
        public ?string $to = null,
        public ?array $tree = null,
        public ?array $patch = null,
        public ?string $locale = null,
    ) {}

    public static function createPage(string $address, array $frontmatter, string $body): self
    {
        return new self(DocsMigrationOperationType::CreatePage, $address, null, $frontmatter, $body);
    }

    public static function updatePage(string $address, ?string $basedOn, array $frontmatter, string $body): self
    {
        return new self(DocsMigrationOperationType::UpdatePage, $address, $basedOn, $frontmatter, $body);
    }

    public static function movePage(string $from, string $to, ?string $basedOn): self
    {
        return new self(DocsMigrationOperationType::MovePage, $from, $basedOn, to: $to);
    }

    public static function deletePage(string $address, ?string $basedOn): self
    {
        return new self(DocsMigrationOperationType::DeletePage, $address, $basedOn);
    }

    public static function updateNavigation(string $version, ?string $basedOn, ?array $tree = null, ?array $patch = null, ?string $locale = null): self
    {
        return new self(DocsMigrationOperationType::UpdateNavigation, $version, $basedOn, tree: $tree, patch: $patch, locale: $locale);
    }

    public static function updateManifest(?string $basedOn, array $patch): self
    {
        return new self(DocsMigrationOperationType::UpdateManifest, '', $basedOn, patch: $patch);
    }

    /** Which page, navigation or manifest this operation touches, for reports. */
    public function subject(): string
    {
        return match ($this->type) {
            DocsMigrationOperationType::UpdateNavigation => 'navigation '.($this->target !== '' ? $this->target : 'default').($this->locale !== null ? '/'.$this->locale : ''),
            DocsMigrationOperationType::UpdateManifest => 'larecipe.json',
            DocsMigrationOperationType::MovePage => "{$this->target} -> {$this->to}",
            default => $this->target,
        };
    }
}
