<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\DTOs\SyncReport;
use LaRecipe\Domains\Sync\Services\CommitMessage;

/**
 * Commits the files a sync run changed on the current branch, and only those,
 * with a message that lists each page and names the agent in a
 * Co-Authored-By trailer. The developer still pushes: the docs travel in the
 * same push as the code (the Same-PR Rule).
 */
final class CommitDocsAction
{
    public function __construct(
        private readonly Vcs $vcs,
        private readonly CommitMessage $message,
    ) {}

    /** @return string|null the new commit's sha, or null when there was nothing to commit or git refused */
    public function execute(SyncReport $report): ?string
    {
        if ($report->files === [] || $report->applied() === []) {
            return null;
        }

        return $this->vcs->commit($report->files, $this->message->build($report));
    }
}
