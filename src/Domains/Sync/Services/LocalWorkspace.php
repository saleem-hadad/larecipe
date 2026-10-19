<?php

namespace LaRecipe\Domains\Sync\Services;

use FilesystemIterator;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\Enums\FileChangeStatus;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * The project's files on the local disk.
 *
 * In dry-run mode, writes land in an in-memory overlay instead of the disk:
 * later reads see the planned contents (so a second step that merges into the
 * same settings file plans the right result), and nothing is written. The
 * change log is shared with copies made by dryRun() and at(), so one command
 * can report everything its steps did.
 */
final class LocalWorkspace implements Workspace
{
    /** @var array<string, string|null> planned contents in dry-run mode; null means deleted */
    private array $overlay = [];

    private ChangeLog $log;

    public function __construct(
        private readonly string $root,
        private readonly bool $dryRun = false,
        ?ChangeLog $log = null,
        private readonly bool $absoluteReports = false,
    ) {
        $this->log = $log ?? new ChangeLog;
    }

    public function root(): string
    {
        return rtrim($this->root, '/');
    }

    public function path(string $relative = ''): string
    {
        $relative = $this->normalize($relative);

        return $relative === '' ? $this->root() : $this->root().'/'.$relative;
    }

    public function exists(string $relative): bool
    {
        $relative = $this->normalize($relative);

        if (array_key_exists($relative, $this->overlay)) {
            return $this->overlay[$relative] !== null;
        }

        return file_exists($this->path($relative));
    }

    public function isDirectory(string $relative): bool
    {
        return is_dir($this->path($relative));
    }

    public function read(string $relative): ?string
    {
        $relative = $this->normalize($relative);

        if (array_key_exists($relative, $this->overlay)) {
            return $this->overlay[$relative];
        }

        $file = $this->path($relative);

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public function write(string $relative, string $contents, bool $executable = false): FileChange
    {
        $relative = $this->normalize($relative);
        $current = $this->read($relative);

        if ($current === $contents) {
            if ($executable && ! $this->dryRun && ! is_executable($this->path($relative))) {
                chmod($this->path($relative), 0755);

                return $this->record($relative, FileChangeStatus::Updated);
            }

            return $this->record($relative, FileChangeStatus::Unchanged);
        }

        $status = $current === null ? FileChangeStatus::Created : FileChangeStatus::Updated;

        if ($this->dryRun) {
            $this->overlay[$relative] = $contents;

            return $this->record($relative, $status);
        }

        $file = $this->path($relative);
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("LaRecipe could not create the folder {$directory}.");
        }

        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException("LaRecipe could not write {$file}.");
        }

        if ($executable) {
            chmod($file, 0755);
        }

        return $this->record($relative, $status);
    }

    public function skip(string $relative, string $reason): FileChange
    {
        return $this->record($this->normalize($relative), FileChangeStatus::Skipped, $reason);
    }

    public function delete(string $relative): FileChange
    {
        $relative = $this->normalize($relative);

        if (! $this->exists($relative)) {
            return $this->record($relative, FileChangeStatus::Unchanged);
        }

        if ($this->dryRun) {
            $this->overlay[$relative] = null;
        } else {
            unlink($this->path($relative));
        }

        return $this->record($relative, FileChangeStatus::Deleted);
    }

    public function files(string $relative): array
    {
        $directory = $this->path($relative);

        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($directory))), '/');
            }
        }

        sort($files);

        return $files;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function dryRun(bool $dryRun = true): Workspace
    {
        return $dryRun === $this->dryRun ? $this : new self($this->root, $dryRun, $this->log, $this->absoluteReports);
    }

    public function at(string $directory): Workspace
    {
        return new self($directory, $this->dryRun, $this->log, true);
    }

    public function changes(): array
    {
        return $this->log->all();
    }

    private function record(string $relative, FileChangeStatus $status, ?string $note = null): FileChange
    {
        return $this->log->record(new FileChange($this->absoluteReports ? $this->path($relative) : $relative, $status, $note));
    }

    /** Relative, forward slashes, no traversal outside the root. */
    private function normalize(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);

        if (str_starts_with($relative, $this->root().'/')) {
            $relative = substr($relative, strlen($this->root()) + 1);
        }

        $relative = trim($relative, '/');

        if (preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            throw new RuntimeException("LaRecipe refuses to touch a path outside the project: {$relative}");
        }

        return $relative;
    }
}
