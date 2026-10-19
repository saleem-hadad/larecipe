<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * A file that belongs to the docs (an image, theme.css), served next to the pages.
 */
final readonly class Asset
{
    public function __construct(
        public string $path,
        public string $mime,
        public ?string $absolutePath = null,
        public ?string $contents = null,
        public ?int $size = null,
        public ?string $hash = null,
    ) {}

    public function contents(): string
    {
        return $this->contents ?? (string) file_get_contents((string) $this->absolutePath);
    }
}
