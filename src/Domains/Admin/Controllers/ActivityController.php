<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\ExportDocsMigrationAction;
use LaRecipe\Domains\Admin\DTOs\MigrationExport;
use LaRecipe\Domains\Admin\Requests\ExportMigrationRequest;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Sources\Actions\ListRevisionsAction;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;

/**
 * Everything that happened to the set, newest first, and the way back into
 * git: changes made in production (here, or by agents outside a pull request)
 * exported as one docs migration file for a pull request. Admin edits never
 * write migration files on their own (ARCHITECTURE.md 5.4); this is the
 * explicit, reviewable way to carry them over.
 */
final class ActivityController
{
    public const PAGE = 50;

    public function __construct(
        private readonly ListRevisionsAction $revisions,
        private readonly ExportDocsMigrationAction $export,
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    public function index(ScopeRequest $request): JsonResponse
    {
        $before = $request->query('before');
        $revisions = $this->revisions->execute($request->docSet()->key, is_string($before) && ctype_digit($before) ? $before : null, self::PAGE);

        return new JsonResponse([
            'revisions' => array_map(fn (Revision $revision) => AdminJson::revision($revision) + [
                'exportable' => $this->exportable($revision),
            ], $revisions),
            'next' => count($revisions) === self::PAGE ? end($revisions)->id : null,
        ]);
    }

    public function export(ExportMigrationRequest $request): JsonResponse
    {
        $result = $this->export->execute(new MigrationExport(
            set: $request->docSet(),
            revisions: $request->revisions(),
            by: $request->author(),
            slug: (string) ($request->input('name') ?: 'admin_changes'),
            summary: $request->summary(),
        ));

        if ($result['operations'] === 0) {
            return new JsonResponse(['message' => 'None of these changes can be exported as a docs migration.'] + $result, 422);
        }

        return new JsonResponse($result + ['file' => $this->relativePath().'/'.$result['name'].'.php']);
    }

    /** A change readers saw that git does not have yet: published outside a pull request. */
    private function exportable(Revision $revision): bool
    {
        return RevisionStatus::from($revision->status)->wasPublished()
            && ! (Channel::tryFrom((string) $revision->channel)?->isReviewedElsewhere() ?? false);
    }

    /** Where docs migrations live, relative to the app root (database/migrations/docs by default). */
    private function relativePath(): string
    {
        $path = (string) $this->config->get('larecipe.migrations.path', $this->app->databasePath('migrations/docs'));
        $base = rtrim($this->app->basePath(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : 'database/migrations/docs';
    }
}
