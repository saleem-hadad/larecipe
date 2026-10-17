<?php

namespace LaRecipe\Domains\Admin\Actions;

use DateTimeImmutable;
use LaRecipe\Domains\Admin\Queries\PendingRevisionsQuery;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Sources\Actions\GetNavigationAction;
use LaRecipe\Domains\Sources\Actions\HashContentAction;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * The page tree of one version and locale: the navigation as navigation.json
 * (or the tree derived from folders, flagged so the editor can say that
 * saving it freezes it), every page with its title and state, and how many
 * changes wait at the pass for each one. Hidden pages and pages outside the
 * navigation are listed too, so nothing is unreachable from the admin.
 */
final class BuildPageTreeAction
{
    public const FRESH_DAYS = 7;

    public function __construct(
        private readonly ListPageSummariesAction $summaries,
        private readonly GetNavigationAction $navigation,
        private readonly HashContentAction $hash,
        private readonly PendingRevisionsQuery $pending,
    ) {}

    public function execute(string $set, string $version, string $locale): array
    {
        $summaries = $this->summaries->execute($set, $version, $locale);
        $tree = $this->navigation->execute($set, $version, $locale, $summaries);
        $pending = array_values(array_filter($this->pending->get($set, $version), fn (Revision $revision) => $revision->ref === null || $revision->ref->locale === $locale));
        $fresh = new DateTimeImmutable('-'.self::FRESH_DAYS.' days');

        return [
            'version' => $version,
            'locale' => $locale,
            'navigation' => [
                'tree' => AdminJson::map($tree->toJson()),
                'derived' => $tree->derived,
                'content_hash' => $this->hash->execute($tree->derived ? null : $tree),
            ],
            'pages' => array_values(array_map(fn (PageSummary $summary) => $summary->toArray() + [
                'private' => $summary->private,
                'fresh' => $summary->updatedAt !== null && $summary->updatedAt > $fresh,
                'pending' => count(array_filter($pending, fn (Revision $revision) => $revision->ref?->path === $summary->ref->path)),
            ], $summaries)),
            // Pages that exist only as a draft or conflict so far (an agent proposed a new page).
            'proposed' => array_values(array_map(
                fn (Revision $revision) => AdminJson::revision($revision),
                array_filter($pending, fn (Revision $revision) => $revision->ref !== null && ! isset($summaries[$revision->ref->path])),
            )),
        ];
    }
}
