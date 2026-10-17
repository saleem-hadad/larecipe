<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * The outcome of a source move, an import or an export: the plan, the
 * validation of the snapshot, whether anything was written, and, with
 * --verify, every content hash that did not match after re-reading the target.
 */
final readonly class MoveReport
{
    /** @param list<string> $mismatches */
    public function __construct(
        public MovePlan $plan,
        public ValidationReport $validation,
        public bool $written,
        public ?bool $verified = null,
        public array $mismatches = [],
        public ?string $destination = null,
    ) {}

    public function ok(): bool
    {
        return ! $this->validation->hasErrors() && $this->mismatches === [];
    }
}
