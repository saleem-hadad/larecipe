<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Admin\Support\SourceText;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\GetRevisionAction;
use LaRecipe\Domains\Sources\Actions\HashContentAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;

/**
 * One revision with both sides of its change, ready to diff: the source text
 * (what a pull request would show) and, for pages, the HTML readers would get.
 *
 * What it is compared with depends on where it stands:
 * - a conflict: production as it was when the docs migration ran (the merge
 *   is written against that, so a newer production change is never lost);
 * - a draft: production now;
 * - a revision in the history: the revision it replaced.
 */
final class DescribeRevisionAction
{
    public function __construct(
        private readonly GetPageAction $pages,
        private readonly GetRevisionAction $revisions,
        private readonly SourceResolver $sources,
        private readonly RenderPreviewAction $render,
        private readonly HashContentAction $hash,
    ) {}

    public function execute(DocSet $set, Revision $revision): array
    {
        [$against, $baseline] = match (true) {
            $revision->is(RevisionStatus::Conflict) => ['conflict', $this->conflictState($revision)],
            $revision->is(RevisionStatus::Draft) => ['production', $this->productionState($set, $revision)],
            default => ['previous', $this->previousState($set, $revision)],
        };

        return [
            'revision' => AdminJson::fullRevision($revision),
            'kind' => $revision->subjectType,
            'against' => $against,
            'proposed' => $this->side($set, $revision, $this->state($revision)),
            'current' => $this->side($set, $revision, $baseline),
        ];
    }

    /** The state a revision leads to: null when it removes its subject. */
    private function state(Revision $revision): ?array
    {
        if ($revision->operation === RevisionOperation::Delete->value) {
            return null;
        }

        return match ($revision->type()) {
            SubjectType::Page => [
                'path' => $revision->ref?->path,
                'frontmatter' => (array) ($revision->payload['frontmatter'] ?? []),
                'body' => (string) ($revision->payload['body'] ?? ''),
            ],
            SubjectType::Navigation => is_array($revision->payload['tree'] ?? null) ? ['tree' => $revision->payload['tree']] : null,
            SubjectType::Manifest => ['manifest' => (array) ($revision->payload['manifest'] ?? [])],
        };
    }

    private function conflictState(Revision $revision): ?array
    {
        $conflict = (array) $revision->conflict;
        $absent = $this->hash->execute(null);

        // Production had nothing there when the docs migration ran.
        if (($conflict['content_hash'] ?? $absent) === $absent) {
            return null;
        }

        return match ($revision->type()) {
            SubjectType::Page => [
                'path' => (string) ($conflict['path'] ?? $revision->refBefore?->path ?? $revision->ref?->path),
                'frontmatter' => (array) ($conflict['frontmatter'] ?? []),
                'body' => (string) ($conflict['body'] ?? ''),
            ],
            SubjectType::Navigation => is_array($conflict['tree'] ?? null) ? ['tree' => $conflict['tree']] : null,
            SubjectType::Manifest => ['manifest' => (array) ($conflict['manifest'] ?? [])],
        };
    }

    private function productionState(DocSet $set, Revision $revision): ?array
    {
        $source = $this->sources->for($set->key);

        if ($revision->type() === SubjectType::Page) {
            $ref = $revision->refBefore ?? $revision->ref;
            $page = $ref !== null ? $this->pages->execute($ref) : null;

            return $page === null ? null : ['path' => $page->ref->path, 'frontmatter' => $page->frontmatter->toArray(), 'body' => $page->body];
        }

        if ($revision->type() === SubjectType::Navigation) {
            [$version, $locale] = array_pad(explode('/', $revision->subject, 2), 2, '');
            $tree = $source->navigation($version, $locale);

            return $tree === null ? null : ['tree' => $tree->toJson()];
        }

        $manifest = $source->manifest();

        return $manifest === null ? null : ['manifest' => $manifest->toArray()];
    }

    private function previousState(DocSet $set, Revision $revision): ?array
    {
        $previous = $revision->replaces !== null ? $this->revisions->execute($set->key, $revision->replaces) : null;

        return $previous === null ? null : $this->state($previous);
    }

    /** One side of the diff, in the shapes the review screens show. */
    private function side(DocSet $set, Revision $revision, ?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return match ($revision->type()) {
            SubjectType::Page => $this->pageSide($set, $revision, $state),
            SubjectType::Navigation => ['tree' => AdminJson::map($state['tree']), 'source' => SourceText::json($state['tree'])],
            SubjectType::Manifest => ['manifest' => AdminJson::map($state['manifest']), 'source' => SourceText::json($state['manifest'])],
        };
    }

    private function pageSide(DocSet $set, Revision $revision, array $state): array
    {
        $base = $revision->ref ?? new PageRef($set->key, $set->defaultVersion()->id, $set->defaultLocale()->code, 'index');
        $page = Page::make($base->withPath((string) ($state['path'] ?? $base->path)), new Frontmatter($state['frontmatter']), $state['body']);

        return [
            'path' => $page->ref->path,
            'title' => $page->title,
            'description' => $page->description,
            'frontmatter' => AdminJson::map($page->frontmatter->toArray()),
            'body' => $page->body,
            'content_hash' => $page->contentHash,
            'source' => SourceText::page($page->frontmatter->toArray(), $page->body),
            'html' => $this->render->execute($set, $page)->html,
        ];
    }
}
