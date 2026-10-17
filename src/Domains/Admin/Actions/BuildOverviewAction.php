<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\Queries\PendingRevisionsQuery;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;

/**
 * The first screen: what state the docs are in. How many pages, how many
 * changes wait at the pass (database sets), and the validation report of the
 * default version, the same one larecipe:validate prints. A repository set
 * gets the same report read-only, with a reminder that its edits belong in git.
 */
final class BuildOverviewAction
{
    public function __construct(
        private readonly ListPageSummariesAction $summaries,
        private readonly PendingRevisionsQuery $pending,
        private readonly ValidateDocsAction $validate,
    ) {}

    public function execute(DocSet $set, string $version): array
    {
        $database = $set->config->driver === 'database';
        $pages = 0;

        foreach ($set->locales() as $locale) {
            $pages += count($this->summaries->execute($set->key, $version, $locale->code));
        }

        $pending = $database ? $this->pending->get($set->key) : [];

        return [
            'driver' => $set->config->driver,
            'version' => $version,
            'review' => $set->config->review,
            'counts' => [
                'pages' => $pages,
                'conflicts' => count(array_filter($pending, fn (Revision $revision) => $revision->is(RevisionStatus::Conflict))),
                'drafts' => count(array_filter($pending, fn (Revision $revision) => $revision->is(RevisionStatus::Draft))),
            ],
            'validation' => $this->validate->execute($set->key, $version)->toArray(),
        ];
    }
}
