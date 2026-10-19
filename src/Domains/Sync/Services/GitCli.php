<?php

namespace LaRecipe\Domains\Sync\Services;

use DateTimeImmutable;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\DTOs\Commit;
use Symfony\Component\Process\Process;

/**
 * The git implementation of Vcs, running the `git` binary.
 *
 * Every command runs in the project root with paths relative to it
 * (`--relative` for diffs), so an app that lives in a subfolder of a larger
 * repository sees only its own files. Failures (no repository, an unknown
 * ref, no commits yet) return empty answers instead of throwing, because
 * drift detection must never break a push or a session.
 */
final class GitCli implements Vcs
{
    private const SEPARATOR = "\x1f";

    private ?bool $available = null;

    private ?string $prefix = null;

    public function __construct(
        private readonly string $root,
        private readonly string $binary = 'git',
    ) {}

    public function available(): bool
    {
        return $this->available ??= is_dir($this->root) && trim((string) $this->git(['rev-parse', '--is-inside-work-tree'])) === 'true';
    }

    public function changedFiles(?string $base, string $head, bool $uncommitted): array
    {
        if (! $this->available()) {
            return [];
        }

        $files = [];

        if ($base !== null) {
            $files = $this->lines($this->git(['diff', '--name-only', '--no-renames', '--relative', '-z', "{$base}...{$head}"]), "\0");
        }

        if ($uncommitted || $base === null) {
            $files = [
                ...$files,
                ...$this->lines($this->git(['diff', '--name-only', '--no-renames', '--relative', '-z']), "\0"),
                ...$this->lines($this->git(['diff', '--name-only', '--no-renames', '--relative', '-z', '--cached']), "\0"),
                ...$this->lines($this->git(['ls-files', '--others', '--exclude-standard', '-z']), "\0"),
            ];
        }

        $files = array_values(array_unique(array_filter($files, fn (string $file) => $file !== '')));
        sort($files);

        return $files;
    }

    public function changedLines(?string $base, string $head, bool $uncommitted, string $file): ?array
    {
        if (! $this->available()) {
            return null;
        }

        if ($this->git(['ls-files', '--error-unmatch', '--', $file]) === null) {
            return null; // untracked: the whole file is new
        }

        $ranges = [];
        $diffs = [];

        if ($base !== null) {
            $diffs[] = $this->git(['diff', '-U0', '--no-renames', '--relative', "{$base}...{$head}", '--', $file]);
        }

        if ($uncommitted || $base === null) {
            $diffs[] = $this->git(['diff', '-U0', '--no-renames', '--relative', 'HEAD', '--', $file]);
        }

        foreach ($diffs as $diff) {
            if ($diff === null) {
                return null;
            }

            if (str_contains($diff, 'Binary files')) {
                return null;
            }

            preg_match_all('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/m', $diff, $hunks, PREG_SET_ORDER);

            foreach ($hunks as $hunk) {
                $start = (int) $hunk[1];
                $count = isset($hunk[2]) ? (int) $hunk[2] : 1;
                // A pure deletion ("+12,0") removed lines right after line 12.
                $ranges[] = $count === 0 ? [max(1, $start), $start + 1] : [$start, $start + $count - 1];
            }
        }

        return $ranges;
    }

    public function lastCommit(string $file): ?Commit
    {
        return $this->commits(['log', '-1', $this->format(), '--', $file])[0] ?? null;
    }

    public function firstCommitTouching(array $globs, Commit|DateTimeImmutable $since): ?Commit
    {
        if ($globs === [] || ! $this->available() || $this->headSha() === null) {
            return null;
        }

        $range = $since instanceof Commit
            ? ["{$since->sha}..HEAD"]
            : ['--since='.$since->format(DATE_ATOM), 'HEAD'];

        $commits = $this->commits(['log', '--reverse', $this->format(), ...$range, '--', ...array_map($this->pathspec(...), $globs)]);

        return $commits[0] ?? null;
    }

    public function defaultBranch(): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $remoteHead = trim((string) $this->git(['symbolic-ref', '--quiet', '--short', 'refs/remotes/origin/HEAD']));

        if ($remoteHead !== '') {
            return preg_replace('#^origin/#', '', $remoteHead);
        }

        foreach (['main', 'master', 'trunk', 'develop'] as $candidate) {
            if ($this->refExists("refs/heads/{$candidate}") || $this->refExists("refs/remotes/origin/{$candidate}")) {
                return $candidate;
            }
        }

        return null;
    }

    public function currentBranch(): ?string
    {
        $branch = trim((string) $this->git(['symbolic-ref', '--quiet', '--short', 'HEAD']));

        return $branch === '' ? null : $branch;
    }

    public function headSha(): ?string
    {
        $sha = trim((string) $this->git(['rev-parse', '--verify', '--quiet', 'HEAD']));

        return $sha === '' ? null : $sha;
    }

    public function upstream(): ?string
    {
        $upstream = trim((string) $this->git(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']));

        return $upstream === '' ? null : $upstream;
    }

    public function refExists(string $ref): bool
    {
        return trim((string) $this->git(['rev-parse', '--verify', '--quiet', $ref.'^{commit}'])) !== '';
    }

    public function hooksPath(): ?string
    {
        $path = trim((string) $this->git(['rev-parse', '--git-path', 'hooks']));

        return $path === '' ? null : $path;
    }

    public function projectPrefix(): string
    {
        return $this->prefix ??= trim((string) $this->git(['rev-parse', '--show-prefix']));
    }

    public function diff(?string $base, string $head, bool $uncommitted, array $files): ?string
    {
        if ($files === []) {
            return '';
        }

        if (! $this->available()) {
            return null;
        }

        $parts = [];

        if ($base !== null) {
            $parts[] = $this->git(['diff', '--no-renames', '--relative', "{$base}...{$head}", '--', ...$files]);
        }

        if ($uncommitted || $base === null) {
            // Before the first commit there is no HEAD to compare with: show what is staged.
            $parts[] = $this->headSha() === null
                ? $this->git(['diff', '--no-renames', '--relative', '--cached', '--', ...$files])
                : $this->git(['diff', '--no-renames', '--relative', 'HEAD', '--', ...$files]);
        }

        if (in_array(null, $parts, true)) {
            return null;
        }

        return implode('', $parts);
    }

    public function commit(array $files, string $message): ?string
    {
        if ($files === [] || ! $this->available()) {
            return null;
        }

        // -A stages deletions too; --only commits these paths and nothing else that was staged.
        if ($this->git(['add', '-A', '--', ...$files]) === null) {
            return null;
        }

        if ($this->git(['commit', '-q', '--only', '-m', $message, '--', ...$files]) === null) {
            return null;
        }

        return $this->headSha();
    }

    /**
     * Glob sources become git "glob" pathspecs; plain paths stay literal so a
     * folder name still matches everything inside it.
     */
    private function pathspec(string $glob): string
    {
        return strpbrk($glob, '*?[{') === false ? $glob : ':(glob)'.$glob;
    }

    private function format(): string
    {
        return '--format=%H'.self::SEPARATOR.'%cI'.self::SEPARATOR.'%s';
    }

    /** @return list<Commit> */
    private function commits(array $arguments): array
    {
        $commits = [];

        foreach ($this->lines($this->git($arguments), "\n") as $line) {
            $parts = explode(self::SEPARATOR, $line, 3);

            if (count($parts) >= 2 && preg_match('/^[0-9a-f]{7,64}$/', $parts[0])) {
                $commits[] = new Commit($parts[0], new DateTimeImmutable($parts[1]), $parts[2] ?? '');
            }
        }

        return $commits;
    }

    /** @return list<string> */
    private function lines(?string $output, string $separator): array
    {
        if ($output === null || $output === '') {
            return [];
        }

        return array_values(array_filter(explode($separator, $output), fn (string $line) => trim($line) !== ''));
    }

    /** The command's stdout, or null when it failed. */
    private function git(array $arguments): ?string
    {
        if (! is_dir($this->root)) {
            return null;
        }

        $process = new Process([$this->binary, ...$arguments], $this->root, ['GIT_TERMINAL_PROMPT' => '0', 'GIT_OPTIONAL_LOCKS' => '0']);
        $process->setTimeout(60);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
