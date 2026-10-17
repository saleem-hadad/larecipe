<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\DTOs\Asset;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Exceptions\RevisionNotFound;
use LaRecipe\Domains\Sources\Exceptions\UnsafePath;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Services\SetLayout;
use LaRecipe\Domains\Sources\Support\MimeTypes;
use LaRecipe\Domains\Sources\Support\PathGuard;
use LaRecipe\Support\Contracts\Clock;

/**
 * Docs stored in the app's database, for teams that manage them in the admin
 * UI (ARCHITECTURE.md 5.3). Every query is scoped by set key, so one
 * connection hosts many sets.
 *
 * The page, navigation and manifest rows hold what readers see now. Every
 * change, live or not, is a revision with its full payload and provenance:
 * publishing applies a revision to its row, a draft or conflict waits in the
 * review queue, and restoring an old revision writes a new one. A deleted page
 * keeps its row, so its history survives and it can be restored.
 *
 * Never served directly: CachedSource wraps it (the Cached-Database Rule).
 */
final class DatabaseSource implements DraftableContentSource
{
    public function __construct(
        private readonly string $set,
        private readonly ?string $connection,
        private readonly SourceModels $models,
        private readonly ConnectionResolverInterface $database,
        private readonly Filesystems $filesystems,
        private readonly ?string $disk,
        private readonly Clock $clock,
    ) {}

    public function set(): string
    {
        return $this->set;
    }

    public function manifest(): ?Manifest
    {
        $row = $this->query('manifest')->where('set', $this->set)->first();

        if ($row === null || $row->published_revision_id === null || $row->manifest === null) {
            return null;
        }

        return Manifest::fromArray($row->manifest, ucfirst($this->set));
    }

    public function pages(string $version, string $locale): iterable
    {
        return $this->livePages()
            ->where('version', $version)
            ->where('locale', $locale)
            ->orderBy('path')
            ->pluck('path')
            ->map(fn (string $path) => new PageRef($this->set, $version, $locale, $path))
            ->all();
    }

    public function page(PageRef $ref): ?Page
    {
        $row = $this->liveRow($ref);

        if ($row === null) {
            return null;
        }

        $revision = $row->published_revision_id !== null ? $this->query('revision')->find($row->published_revision_id) : null;
        $provenance = $revision !== null ? $this->provenance($revision) : null;

        return Page::make(
            $ref,
            new Frontmatter($row->frontmatter ?? []),
            (string) $row->body,
            $provenance?->at ?? $this->immutable($row->updated_at),
            $provenance,
        );
    }

    /**
     * A locale without its own navigation uses the default locale's, exactly
     * like a repository set's shared navigation.json in the version folder.
     */
    public function navigation(string $version, string $locale): ?NavigationTree
    {
        $row = $this->liveNavigation($version, $locale);

        if ($row === null) {
            $default = SetLayout::defaultLocale($this->manifest());
            $row = $default !== $locale ? $this->liveNavigation($version, $default) : null;
        }

        return $row !== null ? NavigationTree::fromJson($row->tree) : null;
    }

    public function asset(string $version, string $path): ?Asset
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $candidates = [[$version, $path], [$version, '_assets/'.$path], [Version::IMPLICIT, '_assets/'.$path]];

        foreach ($candidates as [$candidateVersion, $candidatePath]) {
            $row = $this->query('asset')->where('set', $this->set)->where('version', $candidateVersion)->where('path', $candidatePath)->first();

            if ($row !== null) {
                $disk = $this->filesystems->disk($row->disk);

                if (! $disk->exists($row->disk_path)) {
                    return null;
                }

                return new Asset($path, $row->mime, null, (string) $disk->get($row->disk_path), $row->size, $row->hash);
            }
        }

        return null;
    }

    /**
     * Changes whenever live content changes: the highest published revision id
     * plus the number of publishes (a draft published late has a lower id than
     * revisions published before it), plus the asset table's state.
     */
    public function fingerprint(?string $version = null): string
    {
        $revisions = $this->query('revision')->where('set', $this->set)->whereNotNull('published_at')
            ->selectRaw('count(*) as aggregate_count, max(id) as aggregate_max')->first();
        $assets = $this->query('asset')->where('set', $this->set)
            ->selectRaw('count(*) as aggregate_count, max(updated_at) as aggregate_max')->first();

        return 'r'.(int) $revisions?->aggregate_max.'.'.(int) $revisions?->aggregate_count
            .'-'.substr(hash('xxh128', (int) $assets?->aggregate_count.'|'.$assets?->aggregate_max), 0, 8);
    }

    public function writePage(Page $page, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->transaction(function () use ($page, $by, $mode, $context) {
            $row = $this->pageRow($page->ref) ?? $this->newPageRow($page->ref);

            $revision = $this->record($row, SubjectType::Page, RevisionOperation::Write, $mode, $by, $context, [
                'version' => $page->ref->version,
                'locale' => $page->ref->locale,
                'path' => $page->ref->path,
                'payload' => ['frontmatter' => $page->frontmatter->toArray(), 'body' => $page->body],
                'content_hash' => $page->contentHash,
                'conflict' => $mode === WriteMode::Conflict ? $this->pageSnapshot($row) : null,
            ]);

            return $this->finish($row, $revision, $mode);
        });
    }

    public function movePage(PageRef $from, PageRef $to, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->transaction(function () use ($from, $to, $by, $mode, $context) {
            $row = $this->liveRow($from) ?? throw InvalidContentChange::missingPage($from->path);
            $taken = $this->liveRow($to) !== null;

            if ($taken && $mode !== WriteMode::Conflict) {
                throw InvalidContentChange::pathTaken($to->path);
            }

            $revision = $this->record($row, SubjectType::Page, RevisionOperation::Move, $mode, $by, $context, [
                'version' => $to->version,
                'locale' => $to->locale,
                'path' => $to->path,
                'path_before' => $from->path,
                'payload' => ['frontmatter' => $row->frontmatter ?? [], 'body' => (string) $row->body, 'from' => $from->toArray()],
                'content_hash' => $row->content_hash,
                'conflict' => $mode === WriteMode::Conflict ? $this->pageSnapshot($row) + ['target_taken' => $taken] : null,
            ]);

            return $this->finish($row, $revision, $mode);
        });
    }

    public function deletePage(PageRef $ref, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->transaction(function () use ($ref, $by, $mode, $context) {
            $row = $this->liveRow($ref) ?? throw InvalidContentChange::missingPage($ref->path);

            $revision = $this->record($row, SubjectType::Page, RevisionOperation::Delete, $mode, $by, $context, [
                'version' => $ref->version,
                'locale' => $ref->locale,
                'path' => $ref->path,
                'payload' => ['frontmatter' => $row->frontmatter ?? [], 'body' => (string) $row->body],
                'conflict' => $mode === WriteMode::Conflict ? $this->pageSnapshot($row) : null,
            ]);

            return $this->finish($row, $revision, $mode);
        });
    }

    public function writeNavigation(string $version, string $locale, NavigationTree $tree, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->writeNavigationState($version, $locale, $tree, RevisionOperation::Write, $by, $mode, $context);
    }

    public function removeNavigation(string $version, string $locale, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->writeNavigationState($version, $locale, null, RevisionOperation::Delete, $by, $mode, $context);
    }

    public function writeManifest(Manifest $manifest, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision
    {
        return $this->transaction(function () use ($manifest, $by, $mode, $context) {
            $row = $this->query('manifest')->where('set', $this->set)->first() ?? $this->create('manifest', ['set' => $this->set]);

            $revision = $this->record($row, SubjectType::Manifest, RevisionOperation::Write, $mode, $by, $context, [
                'payload' => ['manifest' => $manifest->toArray()],
                'content_hash' => ContentHashes::manifest($manifest),
                'conflict' => $mode === WriteMode::Conflict ? $this->manifestSnapshot($row) : null,
            ]);

            return $this->finish($row, $revision, $mode);
        });
    }

    public function writeAsset(string $version, string $path, string $contents): void
    {
        $version = PathGuard::segment($version);
        $path = PathGuard::relative($path);

        if (str_ends_with(strtolower($path), '.md')) {
            throw UnsafePath::for($path);
        }

        $diskPath = "larecipe/{$this->set}/{$version}/{$path}";
        $this->disk()->put($diskPath, $contents);

        $row = $this->query('asset')->where('set', $this->set)->where('version', $version)->where('path', $path)->first()
            ?? $this->models->make('asset', $this->connection)->fill(['set' => $this->set, 'version' => $version, 'path' => $path]);

        $row->fill([
            'disk' => $this->disk,
            'disk_path' => $diskPath,
            'mime' => MimeTypes::for($path),
            'size' => strlen($contents),
            'hash' => ContentHashes::asset($contents),
        ]);

        // A rewrite with new contents must move the fingerprint even when the row already existed.
        $row->updated_at = $this->now();
        $row->save();
    }

    public function deleteAsset(string $version, string $path): void
    {
        $row = $this->query('asset')->where('set', $this->set)->where('version', $version)->where('path', ltrim($path, '/'))->first();

        if ($row !== null) {
            $this->filesystems->disk($row->disk)->delete($row->disk_path);
            $row->delete();
        }
    }

    public function assets(string $version): iterable
    {
        return $this->query('asset')->where('set', $this->set)->where('version', $version)->orderBy('path')->pluck('path')->all();
    }

    public function drafts(?string $version = null): iterable
    {
        return $this->query('revision')
            ->where('set', $this->set)
            ->whereIn('status', [RevisionStatus::Draft->value, RevisionStatus::Conflict->value])
            ->when($version !== null, fn (Builder $query) => $query->where('version', $version))
            ->orderBy('id')
            ->get()
            ->map(fn (Model $row) => $this->toRevision($row))
            ->all();
    }

    public function publish(Revision $draft, Provenance $by): void
    {
        $this->transaction(function () use ($draft, $by) {
            $revision = $this->revisionRow($draft);

            if (! RevisionStatus::from($revision->status)->isPending()) {
                throw RevisionNotFound::notPending((string) $revision->id, $revision->status);
            }

            $row = $this->subjectRow($revision);

            if ($revision->subject_type === SubjectType::Page->value && $revision->operation !== RevisionOperation::Delete->value) {
                $occupant = $this->liveRow(new PageRef($this->set, (string) $revision->version, (string) $revision->locale, (string) $revision->path));

                if ($occupant !== null && $occupant->getKey() !== $row->getKey()) {
                    throw InvalidContentChange::pathTaken((string) $revision->path);
                }
            }

            $now = $this->now();
            $revision->fill([
                'status' => RevisionStatus::Published->value,
                'replaces_id' => $row->published_revision_id,
                'published_at' => $now,
                'reviewed_by' => $by->name,
                'reviewed_at' => $now,
            ])->save();

            $this->apply($row, $revision);
        });
    }

    public function reject(Revision $draft, Provenance $by, ?string $reason = null): void
    {
        $revision = $this->revisionRow($draft);

        $revision->fill([
            'status' => RevisionStatus::Rejected->value,
            'reviewed_by' => $by->name,
            'reviewed_at' => $this->now(),
            'review_note' => $reason,
        ])->save();
    }

    public function revert(PageRef $page, Revision $to, Provenance $by): Revision
    {
        return $this->restore($to, $by);
    }

    public function restore(Revision $to, Provenance $by, ?WriteContext $context = null): Revision
    {
        return $this->transaction(function () use ($to, $by, $context) {
            $target = $this->revisionRow($to);
            $row = $this->subjectRow($target);
            $previous = $row->published_revision_id;
            $context ??= new WriteContext(summary: "Revert to revision #{$target->id}");
            $type = SubjectType::from($target->subject_type);
            $removes = $target->operation === RevisionOperation::Delete->value;

            $attributes = match ($type) {
                SubjectType::Page => [
                    'version' => $target->version,
                    'locale' => $target->locale,
                    'path' => $target->path,
                    'path_before' => $row->path !== $target->path ? $row->path : null,
                    'payload' => ['frontmatter' => $target->payload['frontmatter'] ?? [], 'body' => (string) ($target->payload['body'] ?? '')],
                    'content_hash' => $removes ? null : Page::hash(new Frontmatter($target->payload['frontmatter'] ?? []), (string) ($target->payload['body'] ?? '')),
                ],
                SubjectType::Navigation => [
                    'version' => $row->version,
                    'locale' => $row->locale,
                    'payload' => ['tree' => $removes ? null : ($target->payload['tree'] ?? null)],
                    'content_hash' => $removes ? null : $target->content_hash,
                ],
                SubjectType::Manifest => [
                    'payload' => ['manifest' => $target->payload['manifest'] ?? []],
                    'content_hash' => $target->content_hash,
                ],
            };

            if ($type === SubjectType::Page && ! $removes) {
                $occupant = $this->liveRow(new PageRef($this->set, (string) $target->version, (string) $target->locale, (string) $target->path));

                if ($occupant !== null && $occupant->getKey() !== $row->getKey()) {
                    throw InvalidContentChange::pathTaken((string) $target->path);
                }
            }

            $operation = $removes ? RevisionOperation::Delete : RevisionOperation::Write;
            $revision = $this->record($row, $type, $operation, WriteMode::Publish, $by, $context, $attributes);
            $this->apply($row, $revision);

            if ($previous !== null && $previous !== $revision->id) {
                $this->query('revision')->whereKey($previous)->update(['status' => RevisionStatus::Reverted->value]);
            }

            return $this->toRevision($revision->refresh());
        });
    }

    public function revision(string $id): ?Revision
    {
        $row = $this->query('revision')->where('set', $this->set)->whereKey($id)->first();

        return $row !== null ? $this->toRevision($row) : null;
    }

    public function history(Subject $subject): iterable
    {
        $row = match ($subject->type) {
            SubjectType::Page => $subject->ref !== null ? $this->pageRow($subject->ref) : null,
            SubjectType::Navigation => $this->query('navigation')->where('set', $this->set)->where('version', $subject->version)->where('locale', $subject->locale)->first(),
            SubjectType::Manifest => $this->query('manifest')->where('set', $this->set)->first(),
        };

        if ($row === null) {
            return [];
        }

        return $this->query('revision')
            ->where('set', $this->set)
            ->where('subject_type', $subject->type->value)
            ->where('subject_id', $row->getKey())
            ->orderByDesc('id')
            ->get()
            ->map(fn (Model $revision) => $this->toRevision($revision))
            ->all();
    }

    public function revisions(?string $before = null, int $limit = 50): iterable
    {
        return $this->query('revision')
            ->where('set', $this->set)
            ->when($before !== null, fn (Builder $query) => $query->where('id', '<', (int) $before))
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Model $revision) => $this->toRevision($revision))
            ->all();
    }

    private function writeNavigationState(string $version, string $locale, ?NavigationTree $tree, RevisionOperation $operation, Provenance $by, WriteMode $mode, ?WriteContext $context): Revision
    {
        return $this->transaction(function () use ($version, $locale, $tree, $operation, $by, $mode, $context) {
            $row = $this->query('navigation')->where('set', $this->set)->where('version', $version)->where('locale', $locale)->first()
                ?? $this->create('navigation', ['set' => $this->set, 'version' => $version, 'locale' => $locale]);

            $revision = $this->record($row, SubjectType::Navigation, $operation, $mode, $by, $context, [
                'version' => $version,
                'locale' => $locale,
                'payload' => ['tree' => $tree?->toJson()],
                'content_hash' => $tree !== null ? ContentHashes::navigation($tree) : null,
                'conflict' => $mode === WriteMode::Conflict ? ['content_hash' => $row->published_revision_id !== null && $row->tree !== null ? $row->content_hash : ContentHashes::ABSENT, 'tree' => $row->tree] : null,
            ]);

            return $this->finish($row, $revision, $mode);
        });
    }

    /** Create the revision row for a change to $row. */
    private function record(Model $row, SubjectType $type, RevisionOperation $operation, WriteMode $mode, Provenance $by, ?WriteContext $context, array $attributes): Model
    {
        $now = $this->now();

        return $this->create('revision', $attributes + [
            'set' => $this->set,
            'subject_type' => $type->value,
            'subject_id' => $row->getKey(),
            'operation' => $operation->value,
            'based_on_hash' => $context?->basedOn,
            'replaces_id' => $row->published_revision_id,
            'status' => match ($mode) {
                WriteMode::Publish => RevisionStatus::Published->value,
                WriteMode::Draft => RevisionStatus::Draft->value,
                WriteMode::Conflict => RevisionStatus::Conflict->value,
            },
            'actor' => $by->actor->value,
            'actor_name' => $by->name,
            'actor_ref' => $by->ref,
            'channel' => $context?->channel->value,
            'migration' => $context?->migration,
            'summary' => $context?->summary !== null ? mb_substr($context->summary, 0, 500) : null,
            'published_at' => $mode === WriteMode::Publish ? $now : null,
            'created_at' => $now,
        ]);
    }

    private function finish(Model $row, Model $revision, WriteMode $mode): Revision
    {
        if ($mode === WriteMode::Publish) {
            $this->apply($row, $revision);
        }

        return $this->toRevision($revision);
    }

    /**
     * Make a published revision what readers see: copy its state onto the
     * subject row and mark the revision that was live before as superseded.
     */
    private function apply(Model $row, Model $revision): void
    {
        $previous = $row->published_revision_id;

        match (SubjectType::from($revision->subject_type)) {
            SubjectType::Page => $this->applyPage($row, $revision),
            SubjectType::Navigation => $row->fill([
                'tree' => $revision->payload['tree'] ?? null,
                'content_hash' => $revision->content_hash,
                'published_revision_id' => $revision->getKey(),
            ])->save(),
            SubjectType::Manifest => $row->fill([
                'manifest' => $revision->payload['manifest'] ?? [],
                'content_hash' => $revision->content_hash,
                'published_revision_id' => $revision->getKey(),
            ])->save(),
        };

        if ($previous !== null && $previous !== $revision->getKey()) {
            $this->query('revision')->whereKey($previous)->where('status', RevisionStatus::Published->value)
                ->update(['status' => RevisionStatus::Superseded->value]);
        }
    }

    private function applyPage(Model $row, Model $revision): void
    {
        if ($revision->operation === RevisionOperation::Delete->value) {
            $row->fill(['deleted_at' => $this->now(), 'published_revision_id' => $revision->getKey()])->save();

            return;
        }

        $ref = new PageRef($this->set, (string) $revision->version, (string) $revision->locale, (string) $revision->path);
        $page = Page::make($ref, new Frontmatter($revision->payload['frontmatter'] ?? []), (string) ($revision->payload['body'] ?? ''));

        $row->fill([
            'version' => $ref->version,
            'locale' => $ref->locale,
            'path' => $ref->path,
            'title' => $page->title,
            'description' => $page->description,
            'frontmatter' => $page->frontmatter->toArray(),
            'body' => $page->body,
            'content_hash' => $page->contentHash,
            'published_revision_id' => $revision->getKey(),
            'deleted_at' => null,
        ])->save();
    }

    private function toRevision(Model $row): Revision
    {
        $type = SubjectType::from($row->subject_type);
        $ref = $type === SubjectType::Page ? new PageRef($this->set, (string) $row->version, (string) $row->locale, (string) $row->path) : null;
        $from = $row->payload['from'] ?? null;

        return new Revision(
            id: (string) $row->getKey(),
            subjectType: $row->subject_type,
            subject: Revision::subjectKey($type, $ref, $row->version, $row->locale),
            operation: $row->operation,
            status: $row->status,
            author: $this->provenance($row),
            contentHash: $row->content_hash,
            basedOnHash: $row->based_on_hash,
            summary: $row->summary,
            createdAt: $this->immutable($row->created_at),
            set: $this->set,
            ref: $ref,
            refBefore: is_array($from) ? new PageRef($this->set, (string) $from['version'], (string) $from['locale'], (string) $from['path']) : null,
            payload: $row->payload ?? [],
            channel: $row->channel,
            replaces: $row->replaces_id !== null ? (string) $row->replaces_id : null,
            conflict: $row->conflict,
            migration: $row->migration,
            reviewedBy: $row->reviewed_by,
            reviewedAt: $this->immutable($row->reviewed_at),
            reviewNote: $row->review_note,
            publishedAt: $this->immutable($row->published_at),
        );
    }

    private function provenance(Model $revision): Provenance
    {
        return new Provenance(
            Actor::tryFrom((string) $revision->actor) ?? Actor::Human,
            $revision->actor_name,
            $revision->actor_ref,
            $this->immutable($revision->published_at ?? $revision->created_at),
            $revision->reviewed_by,
        );
    }

    /** Production's side of a page conflict. */
    private function pageSnapshot(Model $row): array
    {
        if (! $this->isLive($row)) {
            return ['content_hash' => ContentHashes::ABSENT];
        }

        return [
            'content_hash' => $row->content_hash,
            'revision' => $row->published_revision_id !== null ? (string) $row->published_revision_id : null,
            'path' => $row->path,
            'frontmatter' => $row->frontmatter ?? [],
            'body' => (string) $row->body,
        ];
    }

    private function manifestSnapshot(Model $row): array
    {
        return $row->published_revision_id !== null && $row->manifest !== null
            ? ['content_hash' => $row->content_hash, 'manifest' => $row->manifest]
            : ['content_hash' => ContentHashes::ABSENT];
    }

    private function revisionRow(Revision $revision): Model
    {
        return $this->query('revision')->where('set', $this->set)->whereKey((string) $revision->id)->first()
            ?? throw RevisionNotFound::withId($this->set, (string) $revision->id);
    }

    private function subjectRow(Model $revision): Model
    {
        return $this->query($revision->subject_type)->whereKey($revision->subject_id)->firstOrFail();
    }

    private function livePages(): Builder
    {
        return $this->query('page')->where('set', $this->set)->whereNotNull('published_revision_id')->whereNull('deleted_at');
    }

    private function liveRow(PageRef $ref): ?Model
    {
        return $this->livePages()->where('version', $ref->version)->where('locale', $ref->locale)->where('path', $ref->path)->first();
    }

    private function liveNavigation(string $version, string $locale): ?Model
    {
        return $this->query('navigation')->where('set', $this->set)->where('version', $version)->where('locale', $locale)
            ->whereNotNull('published_revision_id')->whereNotNull('tree')->first();
    }

    /** The page's row: the live one, or the latest one that ever had this address (a draft, a deleted page). */
    private function pageRow(PageRef $ref): ?Model
    {
        return $this->liveRow($ref) ?? $this->query('page')->where('set', $this->set)
            ->where('version', $ref->version)->where('locale', $ref->locale)->where('path', $ref->path)
            ->orderByDesc('id')->first();
    }

    private function newPageRow(PageRef $ref): Model
    {
        return $this->create('page', [
            'set' => $this->set,
            'version' => PathGuard::segment($ref->version),
            'locale' => PathGuard::segment($ref->locale),
            'path' => $ref->isIndex() ? 'index' : PathGuard::relative($ref->path),
        ]);
    }

    private function isLive(Model $row): bool
    {
        return $row->published_revision_id !== null && $row->deleted_at === null;
    }

    private function create(string $model, array $attributes): Model
    {
        $row = $this->models->make($model, $this->connection);
        $row->fill($attributes)->save();

        return $row;
    }

    private function query(string $model): Builder
    {
        return $this->models->query($model, $this->connection);
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk($this->disk);
    }

    /** @template T @param Closure(): T $callback @return T */
    private function transaction(Closure $callback): mixed
    {
        return $this->database->connection($this->connection)->transaction($callback);
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function immutable(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
    }
}
