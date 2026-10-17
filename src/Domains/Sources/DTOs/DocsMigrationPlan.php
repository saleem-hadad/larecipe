<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;

/**
 * A docs migration as data: its name, the set it changes, who wrote it (the
 * provenance its revisions carry) and its operations in order.
 */
final readonly class DocsMigrationPlan
{
    /** @param list<DocsMigrationOperation> $operations */
    public function __construct(
        public string $name,
        public string $set,
        public Provenance $author,
        public ?string $summary = null,
        public array $operations = [],
    ) {}

    /** @param list<DocsMigrationOperation> $operations */
    public function withOperations(array $operations): self
    {
        return new self($this->name, $this->set, $this->author, $this->summary, array_values($operations));
    }

    public function withSummary(?string $summary): self
    {
        return new self($this->name, $this->set, $this->author, $summary, $this->operations);
    }
}
