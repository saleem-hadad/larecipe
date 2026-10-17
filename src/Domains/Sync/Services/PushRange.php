<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;

/**
 * Works out what "the change" is at push time: everything on this branch
 * since it left the default branch, which is exactly what its pull request
 * will show. On the default branch itself, the commits not yet pushed.
 */
final class PushRange
{
    public function __construct(
        private readonly Vcs $vcs,
        private readonly SyncSettings $settings,
    ) {}

    /** The default branch's name, for instructions people and agents read. */
    public function defaultBranch(): string
    {
        return $this->settings->defaultBranch ?? $this->vcs->defaultBranch() ?? 'main';
    }

    /** The ref to diff against, or null when only uncommitted changes count. */
    public function base(): ?string
    {
        $branch = $this->defaultBranch();

        if ($this->vcs->currentBranch() === $branch) {
            return $this->vcs->upstream();
        }

        foreach (["origin/{$branch}", $branch] as $candidate) {
            if ($this->vcs->refExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
