<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Symfony\Component\Process\Process;

/**
 * The branch the developer is on, which names the branch's docs migration
 * (database/migrations/docs/{timestamp}_docs_{branch}.php).
 */
class GitBranch
{
    public function __construct(
        private readonly string $directory,
        private readonly string $git = 'git',
    ) {}

    /** The current branch, or null outside a work tree or on a detached HEAD. */
    public function current(): ?string
    {
        try {
            $process = new Process([$this->git, 'rev-parse', '--abbrev-ref', 'HEAD'], $this->directory, timeout: 10);
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        $branch = trim($process->getOutput());

        return $process->isSuccessful() && $branch !== '' && $branch !== 'HEAD' ? $branch : null;
    }
}
