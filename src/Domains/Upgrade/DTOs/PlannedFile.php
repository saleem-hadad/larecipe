<?php

namespace LaRecipe\Domains\Upgrade\DTOs;

/**
 * One file the upgrade writes, moves or removes. Paths are relative to the app's
 * base path. A planned file is only data: nothing touches the disk until the
 * plan is applied.
 */
final readonly class PlannedFile
{
    public const WRITE = 'write';

    public const COPY = 'copy';

    public const DELETE = 'delete';

    public function __construct(
        public string $operation,
        public string $path,
        public ?string $contents = null,
        public ?string $from = null,
        public ?string $summary = null,
    ) {}

    public static function write(string $path, string $contents, ?string $summary = null): self
    {
        return new self(self::WRITE, $path, $contents, null, $summary);
    }

    public static function copy(string $from, string $to): self
    {
        return new self(self::COPY, $to, null, $from, 'moved from '.$from);
    }

    public static function delete(string $path, string $summary): self
    {
        return new self(self::DELETE, $path, null, null, $summary);
    }

    public function toArray(): array
    {
        return array_filter([
            'operation' => $this->operation,
            'path' => $this->path,
            'from' => $this->from,
            'summary' => $this->summary,
        ], fn ($value) => $value !== null);
    }
}
