<?php

namespace LaRecipe\Domains\Sources\Queries;

use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\Services\WritableSources;

/**
 * Every revision of one page, navigation or manifest, newest first, including
 * drafts, rejections and the revisions docs migrations created.
 */
final class RevisionHistoryQuery
{
    public function __construct(private readonly WritableSources $sources) {}

    /** @return list<Revision> */
    public function get(Subject $subject): array
    {
        $history = [];

        foreach ($this->sources->draftable($subject->set)->history($subject) as $revision) {
            $history[] = $revision;
        }

        return $history;
    }

    public function find(string $set, string $id): ?Revision
    {
        return $this->sources->draftable($set)->revision($id);
    }
}
