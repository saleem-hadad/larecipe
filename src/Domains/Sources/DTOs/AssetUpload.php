<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * A file to store next to the docs (an image, theme.css). The path is relative
 * to the version root ("_assets/flow.png"); Version::IMPLICIT holds a versioned
 * set's shared files.
 */
final readonly class AssetUpload
{
    public function __construct(
        public string $set,
        public string $version,
        public string $path,
        public string $contents,
    ) {}
}
