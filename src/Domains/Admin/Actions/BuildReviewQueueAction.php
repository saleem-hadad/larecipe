<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\Queries\PendingRevisionsQuery;
use LaRecipe\Domains\Admin\Queries\RecentAgentChangesQuery;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\ReviewMode;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;

/**
 * The pass: every change waiting for a person, in the order they matter.
 * Docs-migration conflicts first (a deploy's change is held back), then
 * drafts from agents and people. Under `review: auto` agents publish
 * directly, so the pass also lists what they published lately, with the
 * revision a revert would bring back.
 */
final class BuildReviewQueueAction
{
    public function __construct(
        private readonly PendingRevisionsQuery $pending,
        private readonly RecentAgentChangesQuery $recent,
    ) {}

    public function execute(DocSet $set): array
    {
        $pending = $this->pending->get($set->key);
        $mode = ReviewMode::fromConfig($set->config->review);

        return [
            'mode' => $mode->value,
            'conflicts' => $this->tickets(array_filter($pending, fn (Revision $revision) => $revision->is(RevisionStatus::Conflict))),
            'drafts' => $this->tickets(array_filter($pending, fn (Revision $revision) => $revision->is(RevisionStatus::Draft))),
            'recent' => $mode === ReviewMode::Auto ? $this->tickets($this->recent->get($set->key)) : [],
        ];
    }

    /** @param array<Revision> $revisions */
    private function tickets(array $revisions): array
    {
        return array_values(array_map(fn (Revision $revision) => AdminJson::revision($revision) + [
            'title' => $this->title($revision),
        ], $revisions));
    }

    /** What the ticket is about, in words: the page title it carries, or the subject. */
    private function title(Revision $revision): string
    {
        $frontmatter = (array) ($revision->payload['frontmatter'] ?? []);

        if (isset($frontmatter['title']) && is_string($frontmatter['title'])) {
            return $frontmatter['title'];
        }

        if (preg_match('/^#\s+(.+)$/m', (string) ($revision->payload['body'] ?? ''), $matches)) {
            return trim($matches[1]);
        }

        return match ($revision->subjectType) {
            'navigation' => 'Navigation',
            'manifest' => 'Set settings',
            default => (string) $revision->ref?->path,
        };
    }
}
