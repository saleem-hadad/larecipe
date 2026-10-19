<?php

namespace LaRecipe\Domains\Sources\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A translation that fell behind its source page, or one that does not exist yet.
 *
 * "stale" is decided by content hash when the translation records the hash it
 * was translated from (frontmatter translated_from), and by update time
 * (provenance, then file time) otherwise.
 */
final readonly class TranslationStatus
{
    public const STALE = 'stale';

    public const MISSING = 'missing';

    public function __construct(
        public PageRef $source,
        public PageRef $translation,
        public string $status,
        public string $reason,
        public ?string $sourceHash = null,
        public ?string $translatedFrom = null,
        public ?DateTimeImmutable $sourceUpdatedAt = null,
        public ?DateTimeImmutable $translationUpdatedAt = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'path' => $this->source->path,
            'version' => $this->source->version,
            'locale' => $this->translation->locale,
            'status' => $this->status,
            'reason' => $this->reason,
            'source_hash' => $this->sourceHash,
            'translated_from' => $this->translatedFrom,
            'source_updated_at' => $this->sourceUpdatedAt?->format(DATE_ATOM),
            'translation_updated_at' => $this->translationUpdatedAt?->format(DATE_ATOM),
        ], fn ($value) => $value !== null);
    }
}
