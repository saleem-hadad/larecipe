<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\Services\MarkedBlock;

/**
 * Installs (or removes) the optional git pre-push hook for people who push
 * without an agent (ARCHITECTURE.md 14.3).
 *
 * It runs the same deterministic check as the agent hook and, by default,
 * only prints the stale pages and lets the push through; `--strict` makes it
 * block, with `git push --no-verify` as the usual escape. It never calls an
 * LLM. It respects core.hooksPath, and when a pre-push hook already exists it
 * adds a marked block to that script instead of replacing it.
 */
final class InstallGitHookAction
{
    public function __construct(private readonly Vcs $vcs) {}

    public function execute(Workspace $workspace, bool $strict = false, bool $uninstall = false): FileChange
    {
        $hooks = $this->vcs->available() ? $this->vcs->hooksPath() : null;

        if ($hooks === null) {
            return $workspace->skip('.git/hooks/pre-push', 'This project is not inside a git repository.');
        }

        $directory = str_starts_with($hooks, '/') ? $hooks : $workspace->root().'/'.$hooks;
        $directory = $this->resolve($directory);
        $target = $workspace->at($directory);
        $block = MarkedBlock::shell();
        $current = $target->read('pre-push');

        if ($uninstall) {
            if ($current === null || $block->read($current) === null) {
                return $target->skip('pre-push', 'No LaRecipe block to remove.');
            }

            $remaining = (string) $block->remove($current);

            return trim(preg_replace('/^#!.*$/m', '', $remaining) ?? '') === ''
                ? $target->delete('pre-push')
                : $target->write('pre-push', $remaining, executable: true);
        }

        if ($current !== null && $block->read($current) === null && ! preg_match('/^#!.*\b(sh|bash|zsh|dash)\b/', $current)) {
            return $target->skip('pre-push', 'A pre-push hook already exists and is not a shell script. Add this line to it yourself: '.$this->command($strict));
        }

        $contents = $current ?? "#!/bin/sh\n";

        return $target->write('pre-push', $block->write($contents, $this->body($strict)), executable: true);
    }

    private function body(bool $strict): string
    {
        $mode = $strict
            ? '# Blocks a push that leaves docs pages stale. Skip once with: git push --no-verify'
            : '# Lists docs pages this push leaves stale, then lets the push through.';

        return implode("\n", [
            '# LaRecipe docs check. Deterministic: it reads git and the docs, and never calls an AI.',
            $mode,
            '# Turn it off for one push with LARECIPE_HOOKS=off, or remove it: php artisan larecipe:hooks:git --uninstall',
            'if [ "$LARECIPE_HOOKS" != "off" ]; then',
            '  '.$this->command($strict).($strict ? ' || exit 1' : ' || true'),
            'fi',
        ]);
    }

    private function command(bool $strict): string
    {
        $prefix = rtrim($this->vcs->projectPrefix(), '/');
        $directory = $prefix === '' ? '"$(git rev-parse --show-toplevel)"' : '"$(git rev-parse --show-toplevel)/'.$prefix.'"';

        return "(cd {$directory} && php artisan larecipe:hook git-pre-push".($strict ? ' --strict' : '').')';
    }

    /** Collapse "app/../.git/hooks" without requiring the folder to exist yet. */
    private function resolve(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return '/'.implode('/', $parts);
    }
}
