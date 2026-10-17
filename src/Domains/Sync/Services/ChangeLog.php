<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * The ordered list of file changes one command made, shared by the
 * workspace and its dry-run copies.
 */
final class ChangeLog
{
    /** @var list<FileChange> */
    private array $changes = [];

    public function record(FileChange $change): FileChange
    {
        $this->changes[] = $change;

        return $change;
    }

    /** @return list<FileChange> */
    public function all(): array
    {
        return $this->changes;
    }
}
