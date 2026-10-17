<?php

namespace LaRecipe\Domains\Sync\DTOs;

use LaRecipe\Domains\Sync\Enums\FileChangeStatus;

/**
 * What a command did (or, with --dry-run, would do) to one file.
 */
final readonly class FileChange
{
    public function __construct(
        public string $path,
        public FileChangeStatus $status,
        public ?string $note = null,
    ) {}

    public function changed(): bool
    {
        return in_array($this->status, [FileChangeStatus::Created, FileChangeStatus::Updated, FileChangeStatus::Deleted], true);
    }

    public function toArray(): array
    {
        return array_filter(['path' => $this->path, 'status' => $this->status->value, 'note' => $this->note], fn ($v) => $v !== null);
    }
}
