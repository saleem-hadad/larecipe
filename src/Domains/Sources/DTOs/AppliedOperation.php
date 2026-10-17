<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType;
use LaRecipe\Domains\Sources\Enums\WriteOutcome;

/**
 * The bookkeeping of one applied docs-migration operation: the revision it
 * created (published, or a conflict draft) and the revision that was live
 * before, which is what `down()` restores.
 */
final readonly class AppliedOperation
{
    public function __construct(
        public DocsMigrationOperationType $type,
        public string $subject,
        public WriteOutcome $outcome,
        public ?string $revision = null,
        public ?string $replaced = null,
        public ?string $reason = null,
    ) {}

    public static function fromResult(DocsMigrationOperation $operation, WriteResult $result): self
    {
        return new self(
            $operation->type,
            $operation->subject(),
            $result->outcome,
            $result->revision?->id,
            $result->revision?->replaces,
            $result->outcome === WriteOutcome::Conflicted ? $result->message : null,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            DocsMigrationOperationType::from((string) $data['type']),
            (string) ($data['subject'] ?? ''),
            WriteOutcome::from((string) $data['outcome']),
            isset($data['revision']) ? (string) $data['revision'] : null,
            isset($data['replaced']) ? (string) $data['replaced'] : null,
            $data['reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'subject' => $this->subject,
            'outcome' => $this->outcome->value,
            'revision' => $this->revision,
            'replaced' => $this->replaced,
            'reason' => $this->reason,
        ];
    }
}
