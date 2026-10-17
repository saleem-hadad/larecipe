<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Enums\FileChangeStatus;
use LaRecipe\Domains\Sync\Services\MarkedBlock;

/**
 * Adds `@AGENTS.md` to CLAUDE.md, so Claude Code reads the LaRecipe
 * instructions. Only ever called after the user said yes.
 *
 * Claude Code reads AGENTS.md by itself when a project has no CLAUDE.md; once
 * a CLAUDE.md exists, it reads only that file unless it imports AGENTS.md.
 * The import goes in a marked block, so it is easy to find and remove.
 */
final class LinkClaudeMdAction
{
    public function __construct(private readonly SyncSettings $settings) {}

    /**
     * With $apply false, nothing is written or recorded: the returned change
     * says whether the link is needed (updated) or not (unchanged, skipped),
     * so the installer only asks the question when it matters.
     */
    public function execute(Workspace $workspace, bool $apply = true): FileChange
    {
        $contents = $workspace->read('CLAUDE.md');
        $import = '@'.$this->settings->instructionsFile;

        if ($contents === null) {
            $reason = 'There is no CLAUDE.md, so Claude Code reads AGENTS.md directly.';

            return $apply ? $workspace->skip('CLAUDE.md', $reason) : new FileChange('CLAUDE.md', FileChangeStatus::Skipped, $reason);
        }

        if (preg_match('/^\s*'.preg_quote($import, '/').'\s*$/m', $contents)) {
            return $apply ? $workspace->write('CLAUDE.md', $contents) : new FileChange('CLAUDE.md', FileChangeStatus::Unchanged);
        }

        return $apply
            ? $workspace->write('CLAUDE.md', (new MarkedBlock)->write($contents, $import))
            : new FileChange('CLAUDE.md', FileChangeStatus::Updated);
    }
}
