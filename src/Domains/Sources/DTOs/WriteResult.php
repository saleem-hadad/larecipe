<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Sources\Enums\WriteOutcome;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * What a write Action did: the revision it recorded (if any), the validation
 * report of what was written, and a short message for people and agents.
 */
final readonly class WriteResult
{
    public function __construct(
        public WriteOutcome $outcome,
        public ?Revision $revision,
        public ValidationReport $validation,
        public ?string $message = null,
    ) {}

    public static function published(Revision $revision, ValidationReport $validation): self
    {
        return new self(WriteOutcome::Published, $revision, $validation);
    }

    public static function drafted(Revision $revision, ValidationReport $validation): self
    {
        return new self(WriteOutcome::Drafted, $revision, $validation, 'Saved as a draft. A person publishes it from the review queue.');
    }

    public static function conflicted(?Revision $revision, ValidationReport $validation, string $message): self
    {
        return new self(WriteOutcome::Conflicted, $revision, $validation, $message);
    }

    public static function unchanged(ValidationReport $validation): self
    {
        return new self(WriteOutcome::Unchanged, null, $validation, 'Nothing changed: the content is identical.');
    }

    public static function rejected(ValidationReport $validation, string $message): self
    {
        return new self(WriteOutcome::Rejected, null, $validation, $message);
    }

    /** The change was applied or saved for review (not refused). */
    public function ok(): bool
    {
        return in_array($this->outcome, [WriteOutcome::Published, WriteOutcome::Drafted, WriteOutcome::Unchanged], true);
    }

    public function isPublished(): bool
    {
        return $this->outcome === WriteOutcome::Published;
    }

    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'message' => $this->message,
            'revision' => $this->revision?->toArray(),
            'validation' => $this->validation->toArray(),
        ];
    }
}
