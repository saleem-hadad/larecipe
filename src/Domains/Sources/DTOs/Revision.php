<?php

namespace LaRecipe\Domains\Sources\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;

/**
 * One recorded change: a page, the navigation or the manifest, as written by a
 * person, an agent or a docs migration. Repository sources return a revision
 * without an id; their history is git.
 *
 * The payload is the full state the change leads to (frontmatter and body,
 * a navigation tree, a manifest), so any revision can be restored on its own.
 * A conflict draft also carries production's version at the time it was
 * raised, so a reviewer sees both sides.
 */
final readonly class Revision
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $conflict  production's side of a conflict draft
     */
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
        public ?string $set = null,
        public ?PageRef $ref = null,
        public ?PageRef $refBefore = null,
        public array $payload = [],
        public ?string $channel = null,
        public ?string $replaces = null,
        public ?array $conflict = null,
        public ?string $migration = null,
        public ?string $reviewedBy = null,
        public ?DateTimeImmutable $reviewedAt = null,
        public ?string $reviewNote = null,
        public ?DateTimeImmutable $publishedAt = null,
    ) {}

    /**
     * The subject key used in revision lists: "{version}/{locale}/{path}" for a
     * page, "{version}/{locale}" for navigation, "larecipe.json" for the manifest.
     */
    public static function subjectKey(SubjectType $type, ?PageRef $ref = null, ?string $version = null, ?string $locale = null): string
    {
        return match ($type) {
            SubjectType::Page => "{$ref?->version}/{$ref?->locale}/{$ref?->path}",
            SubjectType::Navigation => "{$version}/{$locale}",
            SubjectType::Manifest => 'larecipe.json',
        };
    }

    public function is(RevisionStatus $status): bool
    {
        return $this->status === $status->value;
    }

    public function isPending(): bool
    {
        return RevisionStatus::tryFrom($this->status)?->isPending() ?? false;
    }

    public function type(): SubjectType
    {
        return SubjectType::from($this->subjectType);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'set' => $this->set,
            'subject_type' => $this->subjectType,
            'subject' => $this->subject,
            'ref' => $this->ref?->toArray(),
            'ref_before' => $this->refBefore?->toArray(),
            'operation' => $this->operation,
            'status' => $this->status,
            'author' => $this->author->toArray(),
            'channel' => $this->channel,
            'content_hash' => $this->contentHash,
            'based_on_hash' => $this->basedOnHash,
            'replaces' => $this->replaces,
            'summary' => $this->summary,
            'migration' => $this->migration,
            'payload' => $this->payload,
            'conflict' => $this->conflict,
            'reviewed_by' => $this->reviewedBy,
            'reviewed_at' => $this->reviewedAt?->format(DATE_ATOM),
            'review_note' => $this->reviewNote,
            'published_at' => $this->publishedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}
