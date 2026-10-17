<?php

namespace LaRecipe\Domains\Admin\DTOs;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Provenance;

/**
 * Changes made in production (in the admin, or by agents outside a pull
 * request) that someone wants back in git, as one docs migration: which
 * revisions, what to call the file, and the one-line summary it carries.
 */
final readonly class MigrationExport
{
    /** @param list<string> $revisions revision ids, in any order */
    public function __construct(
        public DocSet $set,
        public array $revisions,
        public Provenance $by,
        public string $slug = 'admin_changes',
        public ?string $summary = null,
        public ?\DateTimeImmutable $at = null,
    ) {}
}
