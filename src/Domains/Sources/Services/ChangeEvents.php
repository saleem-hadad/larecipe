<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Sources\Events\ContentChanged;
use LaRecipe\Domains\Sources\Events\DocsMigrationConflicted;
use LaRecipe\Domains\Sources\Events\DraftCreated;
use LaRecipe\Domains\Sources\Events\PageWritten;
use LaRecipe\Support\Contracts\Dispatcher;

/**
 * The events every write Action dispatches, in one place so each Action tells
 * the same story: PageWritten for page changes, DraftCreated when a change
 * waits for review, ContentChanged when live content moved on (which warms
 * the caches), DocsMigrationConflicted when a migration did not overwrite.
 */
final class ChangeEvents
{
    public function __construct(private readonly Dispatcher $events) {}

    public function written(Revision $revision, string $set): void
    {
        if ($revision->subjectType === SubjectType::Page->value) {
            $this->events->dispatch(new PageWritten($revision));
        }

        if ($revision->is(RevisionStatus::Draft)) {
            $this->events->dispatch(new DraftCreated($revision));

            return;
        }

        if ($revision->is(RevisionStatus::Published)) {
            $this->changed($set, $revision);
        }
    }

    public function conflicted(string $set, ?Revision $revision, string $migration, string $reason): void
    {
        if ($revision !== null && $revision->subjectType === SubjectType::Page->value) {
            $this->events->dispatch(new PageWritten($revision));
        }

        $this->events->dispatch(new DocsMigrationConflicted($set, $migration, $reason, $revision));
    }

    public function changed(string $set, ?Revision $revision = null): void
    {
        $pages = array_values(array_filter([$revision?->refBefore, $revision?->ref], fn ($ref) => $ref !== null && $revision->subjectType === SubjectType::Page->value));

        $this->events->dispatch(new ContentChanged($set, $pages));
    }
}
