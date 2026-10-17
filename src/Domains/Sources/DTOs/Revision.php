<?php

namespace LaRecipe\Domains\Sources\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\Provenance;

/**
 * One recorded change: a page, the navigation or the manifest, as written by a
 * person, an agent or a docs migration. Repository sources return a revision
 * without an id; their history is git.
 */
final readonly class Revision
{
    public function __construct(
        public ?string $id,
        public string $subjectType,
        public string $subject,
        public string $operation,
        public string $status,
        public Provenance $author,
        public ?string $contentHash = null,
        public ?string $basedOnHash = null,
        public ?string $summary = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subjectType,
            'subject' => $this->subject,
            'operation' => $this->operation,
            'status' => $this->status,
            'author' => $this->author->toArray(),
            'content_hash' => $this->contentHash,
            'based_on_hash' => $this->basedOnHash,
            'summary' => $this->summary,
            'created_at' => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}
