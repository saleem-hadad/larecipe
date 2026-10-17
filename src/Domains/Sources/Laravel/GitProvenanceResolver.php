<?php

namespace LaRecipe\Domains\Sources\Laravel;

use LaRecipe\Domains\Sources\Contracts\ProvenanceResolver;
use LaRecipe\Domains\Sources\DTOs\ResolvedProvenance;
use LaRecipe\Domains\Sources\Services\GitLogParser;
use LaRecipe\Domains\Sources\Services\MtimeProvenanceResolver;
use Symfony\Component\Process\Process;

/**
 * Provenance from git history: one `git log` over the set's folder, newest
 * first, so each file gets its last commit, its author, the agent named in a
 * Co-Authored-By trailer and the pull request number.
 *
 * Git is a build-time dependency only. Without git, or outside a work tree,
 * or for files never committed, it falls back to modification times.
 */
final class GitProvenanceResolver implements ProvenanceResolver
{
    public function __construct(
        private readonly GitLogParser $parser = new GitLogParser,
        private readonly MtimeProvenanceResolver $fallback = new MtimeProvenanceResolver,
        private readonly string $git = 'git',
    ) {}

    public function resolve(string $root, array $files, ?string $repositoryUrl = null): ResolvedProvenance
    {
        if (! is_dir($root) || ! $this->insideWorkTree($root)) {
            return $this->fallback->resolve($root, $files);
        }

        $log = new Process([$this->git, 'log', '--relative', '--name-only', '--no-renames', '--format='.GitLogParser::FORMAT, '--', '.'], $root, timeout: 120);
        $log->run();

        if (! $log->isSuccessful()) {
            return $this->fallback->resolve($root, $files);
        }

        $history = $this->parser->parse($log->getOutput(), $repositoryUrl);
        $resolved = array_intersect_key($history, array_flip($files));
        $untracked = array_values(array_diff($files, array_keys($resolved)));

        return new ResolvedProvenance('git', $resolved + $this->fallback->resolve($root, $untracked)->files);
    }

    private function insideWorkTree(string $root): bool
    {
        try {
            $process = new Process([$this->git, 'rev-parse', '--is-inside-work-tree'], $root, timeout: 10);
            $process->run();

            return $process->isSuccessful() && trim($process->getOutput()) === 'true';
        } catch (\Throwable) {
            return false;
        }
    }
}
