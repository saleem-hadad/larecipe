<?php

namespace LaRecipe\Domains\Admin\Actions;

use DateTimeImmutable;
use LaRecipe\Domains\Admin\DTOs\MigrationExport;
use LaRecipe\Domains\Admin\Support\DocsAddress;
use LaRecipe\Domains\Admin\Support\MergePatch;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\GetRevisionAction;
use LaRecipe\Domains\Sources\Actions\HashContentAction;
use LaRecipe\Domains\Sources\Actions\RenderDocsMigrationAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;

/**
 * Turns changes made in production back into a docs migration, so they can
 * ride in a pull request: local databases, staging and CI get them through
 * `php artisan migrate` like any other docs change, and the change is in git.
 *
 * Each revision becomes the operation that replays it, against the hash it
 * was written over (basedOn). Applied in production, where the change is
 * already live, every operation is a no-op; applied anywhere that drifted,
 * it raises a conflict instead of overwriting.
 */
final class ExportDocsMigrationAction
{
    public function __construct(
        private readonly GetRevisionAction $revisions,
        private readonly HashContentAction $hash,
        private readonly RenderDocsMigrationAction $render,
    ) {}

    /** @return array{name: string, source: string, operations: int, included: list<string>, skipped: list<array{id: string, reason: string}>} */
    public function execute(MigrationExport $export): array
    {
        $set = $export->set;
        $operations = [];
        $included = [];
        $skipped = [];

        foreach ($this->load($set->key, $export->revisions, $skipped) as $revision) {
            $operation = $this->operation($set, $revision);

            if (is_string($operation)) {
                $skipped[] = ['id' => (string) $revision->id, 'reason' => $operation];

                continue;
            }

            $operations[] = $operation;
            $included[] = (string) $revision->id;
        }

        $name = ($export->at ?? new DateTimeImmutable)->format('Y_m_d_His').'_docs_'.$this->slug($export->slug);
        $summary = $export->summary ?? 'Changes made in production, exported from the admin UI';
        $plan = new DocsMigrationPlan($name, $set->key, new Provenance(Actor::Migration, $export->by->name, null), $summary, $operations);

        return [
            'name' => $name,
            'source' => $this->render->execute($plan),
            'operations' => count($operations),
            'included' => $included,
            'skipped' => $skipped,
        ];
    }

    /**
     * The revisions to export, oldest first (the order they happened in).
     *
     * @param  list<array{id: string, reason: string}>  $skipped
     * @return list<Revision>
     */
    private function load(string $set, array $ids, array &$skipped): array
    {
        $revisions = [];

        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $revision = $this->revisions->execute($set, $id);
            $reason = match (true) {
                $revision === null => 'There is no such revision.',
                ! RevisionStatus::from($revision->status)->wasPublished() => "It was never published ({$revision->status}).",
                Channel::tryFrom((string) $revision->channel)?->isReviewedElsewhere() ?? false => 'It came from a pull request or a docs migration, so git already has it.',
                default => null,
            };

            if ($reason !== null) {
                $skipped[] = ['id' => $id, 'reason' => $reason];

                continue;
            }

            $revisions[] = $revision;
        }

        usort($revisions, fn (Revision $a, Revision $b) => (int) $a->id <=> (int) $b->id);

        return $revisions;
    }

    /** The operation that replays one revision, or why it cannot be exported. */
    private function operation(DocSet $set, Revision $revision): DocsMigrationOperation|string
    {
        return match ($revision->type()) {
            SubjectType::Page => $this->pageOperation($set, $revision),
            SubjectType::Navigation => $this->navigationOperation($set, $revision),
            SubjectType::Manifest => $this->manifestOperation($set, $revision),
        };
    }

    private function pageOperation(DocSet $set, Revision $revision): DocsMigrationOperation|string
    {
        if ($revision->ref === null) {
            return 'The revision does not name its page.';
        }

        $address = DocsAddress::of($set, $revision->ref);
        $frontmatter = (array) ($revision->payload['frontmatter'] ?? []);
        $body = (string) ($revision->payload['body'] ?? '');

        return match ($revision->operation) {
            RevisionOperation::Move->value => $revision->refBefore !== null
                ? DocsMigrationOperation::movePage(DocsAddress::of($set, $revision->refBefore), $address, $revision->basedOnHash)
                : 'The move does not say where the page came from.',
            RevisionOperation::Delete->value => DocsMigrationOperation::deletePage($address, $revision->basedOnHash),
            default => $revision->basedOnHash === $this->hash->execute(null)
                ? DocsMigrationOperation::createPage($address, $frontmatter, $body)
                : DocsMigrationOperation::updatePage($address, $revision->basedOnHash, $frontmatter, $body),
        };
    }

    private function navigationOperation(DocSet $set, Revision $revision): DocsMigrationOperation|string
    {
        $tree = $revision->payload['tree'] ?? null;

        if ($revision->operation === RevisionOperation::Delete->value || ! is_array($tree)) {
            return 'Going back to derived navigation has no docs migration operation; remove the navigation in the pull request instead.';
        }

        [$version, $locale] = array_pad(explode('/', $revision->subject, 2), 2, '');

        return DocsMigrationOperation::updateNavigation(
            $set->isVersioned() ? $version : '',
            $revision->basedOnHash,
            $tree,
            locale: $locale !== $set->defaultLocale()->code ? $locale : null,
        );
    }

    private function manifestOperation(DocSet $set, Revision $revision): DocsMigrationOperation|string
    {
        $previous = $revision->replaces !== null ? $this->revisions->execute($set->key, $revision->replaces) : null;
        $patch = MergePatch::between((array) ($previous?->payload['manifest'] ?? []), (array) ($revision->payload['manifest'] ?? []));

        return $patch === [] ? 'It did not change larecipe.json.' : DocsMigrationOperation::updateManifest($revision->basedOnHash, $patch);
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_') ?: 'admin_changes';
    }
}
