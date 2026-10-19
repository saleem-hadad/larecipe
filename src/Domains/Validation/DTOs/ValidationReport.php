<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * The result of validating one page or a whole set: what `larecipe:validate --json` prints.
 */
final readonly class ValidationReport
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(
        public array $issues = [],
        public int $pages = 0,
    ) {}

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->level === ValidationIssue::ERROR) {
                return true;
            }
        }

        return false;
    }

    public function errors(): array
    {
        return array_values(array_filter($this->issues, fn (ValidationIssue $i) => $i->level === ValidationIssue::ERROR));
    }

    public function warnings(): array
    {
        return array_values(array_filter($this->issues, fn (ValidationIssue $i) => $i->level === ValidationIssue::WARNING));
    }

    public function merge(self $other): self
    {
        return new self([...$this->issues, ...$other->issues], $this->pages + $other->pages);
    }

    public function toArray(): array
    {
        return [
            'ok' => ! $this->hasErrors(),
            'summary' => ['pages' => $this->pages, 'errors' => count($this->errors()), 'warnings' => count($this->warnings())],
            'issues' => array_map(fn (ValidationIssue $issue) => $issue->toArray(), $this->issues),
        ];
    }
}
