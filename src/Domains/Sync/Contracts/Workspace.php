<?php

namespace LaRecipe\Domains\Sync\Contracts;

use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * The project's files, as LaRecipe's installers and generators see them.
 *
 * Every write goes through here so that `--dry-run` is honest (nothing is
 * written, but later steps read the planned contents), and so every command
 * can report exactly which files it created, updated or left alone. Paths are
 * relative to the project root.
 */
interface Workspace
{
    public function root(): string;

    public function path(string $relative = ''): string;

    public function exists(string $relative): bool;

    public function isDirectory(string $relative): bool;

    public function read(string $relative): ?string;

    /**
     * Write a file, creating its folder. Writing the same contents again is
     * reported as unchanged, which is what makes every installer idempotent.
     */
    public function write(string $relative, string $contents, bool $executable = false): FileChange;

    /** Record a file the command chose not to touch, with the reason. */
    public function skip(string $relative, string $reason): FileChange;

    public function delete(string $relative): FileChange;

    /** @return list<string> files under a folder, relative to that folder, sorted */
    public function files(string $relative): array;

    public function isDryRun(): bool;

    /** A copy of this workspace that plans changes without writing them. */
    public function dryRun(bool $dryRun = true): self;

    /**
     * A workspace rooted at another folder (a git hooks folder outside the
     * app), sharing this one's dry-run mode and change log. Its changes are
     * reported with absolute paths.
     */
    public function at(string $directory): self;

    /** @return list<FileChange> every change recorded so far, in order */
    public function changes(): array;
}
