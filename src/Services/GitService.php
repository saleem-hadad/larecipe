<?php

namespace BinaryTorch\LaRecipe\Services;

use BinaryTorch\LaRecipe\Contracts\GitService as GitServiceContract;
use Symfony\Component\Process\Process;
use Illuminate\Support\Collection;

class GitService implements GitServiceContract
{
    public function isGitInstalled(): bool
    {
        $process = new Process(['git', '--version']);
        $process->run();

        return $process->getExitCode() != 127;
    }

    public function getFileShortlog(string $filePath): Collection
    {
        $process = new Process(['git', 'shortlog', '-sn', 'HEAD', '--', $filePath]);
        $process->run();

        return collect(explode("\n", $process->getOutput()))->slice(0, -1)->map(function ($logLine) {
            [$commits, $name] = explode("\t", trim($logLine));
            return compact('commits', 'name');
        });
;
    }
}