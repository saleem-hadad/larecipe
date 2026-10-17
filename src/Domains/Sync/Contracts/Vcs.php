<?php

namespace LaRecipe\Domains\Sync\Contracts;

use LaRecipe\Domains\Sync\DTOs\Commit;

/**
 * The version control questions drift detection asks, and nothing more.
 *
 * Every path in and out is relative to the project root (the Laravel app's base
 * path), even when the app sits in a subfolder of a larger repository. Git is
 * the only implementation today; the contract keeps the Sync domain portable
 * so a standalone CLI can ship it with its own runner.
 */
interface Vcs
{
    /** False when the project is not inside a repository, so callers can degrade gracefully. */
    public function available(): bool;

    /**
     * Files changed between the merge base of $base and $head, plus staged,
     * unstaged and untracked files when $uncommitted is true. With no base,
     * only uncommitted changes are reported.
     *
     * @return list<string>
     */
    public function changedFiles(?string $base, string $head, bool $uncommitted): array;

    /**
     * The line ranges a change touched in the current version of a file, used
     * for `path#region` sources. Null when the answer is unknown (for example a
     * binary file), which callers treat as "the whole file changed".
     *
     * @return list<array{0: int, 1: int}>|null
     */
    public function changedLines(?string $base, string $head, bool $uncommitted, string $file): ?array;

    /** The last commit that touched a file, or null when it was never committed. */
    public function lastCommit(string $file): ?Commit;

    /**
     * The first commit after $since (a commit or a moment) that touched any of
     * the given glob pathspecs, or null when none did.
     *
     * @param  list<string>  $globs
     */
    public function firstCommitTouching(array $globs, Commit|\DateTimeImmutable $since): ?Commit;

    /** The repository's default branch name ("main"), detected from origin/HEAD. */
    public function defaultBranch(): ?string;

    public function currentBranch(): ?string;

    public function headSha(): ?string;

    /** The upstream of the current branch ("origin/feature"), or null. */
    public function upstream(): ?string;

    public function refExists(string $ref): bool;

    /** The directory git runs hooks from, honoring core.hooksPath, relative to the project root when inside it. */
    public function hooksPath(): ?string;

    /** The project root's path relative to the repository root ("" when they are the same). */
    public function projectPrefix(): string;
}
