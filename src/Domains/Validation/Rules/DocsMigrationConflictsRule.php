<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * migrations.conflict: a docs migration met a change made in production since
 * it was written, so its version waits as a conflict draft while production
 * keeps its own. A conflict never fails a deploy; this warning makes sure it
 * is not forgotten in the review queue.
 */
final class DocsMigrationConflictsRule implements SetRule
{
    public function check(DocsSnapshot $docs): array
    {
        $issues = [];

        foreach ($docs->conflicts as $conflict) {
            $migration = $conflict->migration !== null ? "The docs migration {$conflict->migration}" : 'A docs migration';
            $review = '/'.trim($docs->settings->prefix.'/_admin/review/'.$conflict->id, '/');

            $issues[] = ValidationIssue::warning(
                'migrations.conflict',
                "{$migration} conflicts with a change made in production to {$this->subject($conflict)}. Production keeps its version until someone decides.",
                $this->page($docs, $conflict),
                null,
                "Open {$review} in the admin and keep production, take the migration, or edit a merge.",
            );
        }

        return $issues;
    }

    private function subject(Revision $conflict): string
    {
        return match ($conflict->type()) {
            SubjectType::Page => "the page \"{$conflict->ref?->path}\"",
            SubjectType::Navigation => "the navigation of {$conflict->subject}",
            SubjectType::Manifest => 'larecipe.json',
        };
    }

    /** How issues name a page: "2.x/billing", or "billing" on an unversioned set. */
    private function page(DocsSnapshot $docs, Revision $conflict): ?string
    {
        $ref = $conflict->ref;

        if ($conflict->type() !== SubjectType::Page || $ref === null) {
            return null;
        }

        return $docs->version($ref->version, $ref->locale)?->pageId($ref->path) ?? $ref->path;
    }
}
