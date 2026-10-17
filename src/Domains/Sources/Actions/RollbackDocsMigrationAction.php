<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Contracts\DocsMigrationRepository;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\DTOs\AppliedOperation;
use LaRecipe\Domains\Sources\DTOs\MigrationOutcome;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Services\ChangeEvents;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * The built-in `down()` of every docs migration: walks its operations
 * backwards and puts each subject back the way it was, as new revisions.
 * A replaced revision is restored, a created page is removed, a deleted one
 * comes back.
 *
 * When someone changed a subject after the migration (an admin UI edit), the
 * rollback does not clobber that edit: it raises a conflict draft holding the
 * rollback's version and reports it. A conflict the migration raised and no
 * one resolved is simply rejected.
 */
final class RollbackDocsMigrationAction
{
    public function __construct(
        private readonly WritableSources $sources,
        private readonly DocsMigrationRepository $repository,
        private readonly ChangeEvents $events,
    ) {}

    public function execute(string $migration, Provenance $by): MigrationOutcome
    {
        $applied = $this->repository->find($migration);

        if ($applied === null) {
            return new MigrationOutcome($migration, '');
        }

        $source = $this->sources->draftable($applied->set);
        $context = WriteContext::migration($migration, "Roll back {$migration}");
        $warnings = [];

        foreach (array_reverse($applied->operations) as $operation) {
            $warning = $this->rollback($source, $operation, $by, $context, $migration, $applied->set);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        $this->repository->forget($migration);
        $this->events->changed($applied->set);

        return new MigrationOutcome($migration, $applied->set, $applied->operations, $warnings);
    }

    private function rollback(DraftableContentSource $source, AppliedOperation $operation, Provenance $by, WriteContext $context, string $migration, string $set): ?string
    {
        $created = $operation->revision !== null ? $source->revision($operation->revision) : null;

        if ($created === null) {
            return null;
        }

        if ($created->isPending()) {
            $source->reject($created, $by, "Docs migration {$migration} was rolled back.");

            return null;
        }

        $replaced = $operation->replaced !== null ? $source->revision($operation->replaced) : null;

        // Compare content, not statuses: rolling back a later operation of the
        // same migration restores this one's content, which is no edit by anyone.
        if ($this->currentHash($source, $created) !== ($created->contentHash ?? ContentHashes::ABSENT)) {
            return $this->raiseConflict($source, $created, $replaced, $by, $context, $migration, $set);
        }

        if ($replaced !== null) {
            $source->restore($replaced, $by, $context);

            return null;
        }

        // Nothing was live before the migration: the page or navigation it created goes away.
        match ($created->type()) {
            SubjectType::Page => $created->ref !== null && $created->operation !== RevisionOperation::Delete->value
                ? $this->quietly(fn () => $source->deletePage($created->ref, $by, WriteMode::Publish, $context))
                : null,
            SubjectType::Navigation => $this->quietly(fn () => $source->removeNavigation($this->versionOf($created), $this->localeOf($created), $by, WriteMode::Publish, $context)),
            SubjectType::Manifest => null,
        };

        return $created->type() === SubjectType::Manifest
            ? "[larecipe.json] was created by {$migration} and has no earlier version, so it was kept."
            : null;
    }

    /**
     * The subject changed after the migration: keep that change live, and put
     * the rollback's version in the review queue instead.
     */
    private function raiseConflict(DraftableContentSource $source, Revision $created, ?Revision $replaced, Provenance $by, WriteContext $context, string $migration, string $set): string
    {
        $reason = "[{$created->subject}] changed after {$migration} was applied, so its rollback waits as a conflict draft.";

        $revision = $this->quietly(fn () => match ($created->type()) {
            SubjectType::Page => $replaced !== null && $replaced->ref !== null && $replaced->operation !== RevisionOperation::Delete->value
                ? $source->writePage(Page::make($replaced->ref, new Frontmatter((array) ($replaced->payload['frontmatter'] ?? [])), (string) ($replaced->payload['body'] ?? '')), $by, WriteMode::Conflict, $context)
                : ($created->ref !== null ? $source->deletePage($created->ref, $by, WriteMode::Conflict, $context) : null),
            SubjectType::Navigation => is_array($replaced?->payload['tree'] ?? null)
                ? $source->writeNavigation($this->versionOf($created), $this->localeOf($created), NavigationTree::fromJson($replaced->payload['tree']), $by, WriteMode::Conflict, $context)
                : $source->removeNavigation($this->versionOf($created), $this->localeOf($created), $by, WriteMode::Conflict, $context),
            SubjectType::Manifest => $replaced !== null
                ? $source->writeManifest(Manifest::fromArray((array) ($replaced->payload['manifest'] ?? [])), $by, WriteMode::Conflict, $context)
                : null,
        });

        $this->events->conflicted($set, $revision instanceof Revision ? $revision : null, $migration, $reason);

        return $reason;
    }

    private function currentHash(DraftableContentSource $source, Revision $created): string
    {
        return match ($created->type()) {
            SubjectType::Page => $created->ref !== null ? ContentHashes::page($source->page($created->ref)) : ContentHashes::ABSENT,
            SubjectType::Navigation => ContentHashes::navigation($source->navigation($this->versionOf($created), $this->localeOf($created))),
            SubjectType::Manifest => ContentHashes::manifest($source->manifest()),
        };
    }

    private function versionOf(Revision $revision): string
    {
        return explode('/', $revision->subject)[0];
    }

    private function localeOf(Revision $revision): string
    {
        return explode('/', $revision->subject)[1] ?? '';
    }

    /** Run a step whose subject may have moved or vanished since; a rollback reports, it does not crash. */
    private function quietly(\Closure $step): mixed
    {
        try {
            return $step();
        } catch (InvalidContentChange) {
            return null;
        }
    }
}
