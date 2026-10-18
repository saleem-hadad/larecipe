<?php

namespace LaRecipe\Domains\Admin\DTOs;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A whole larecipe.json as the settings screen (or a reviewer editing a
 * manifest draft) wants it to be, with who asks and the hash they read.
 */
final readonly class SettingsChange
{
    /** @param array<string, mixed> $manifest */
    public function __construct(
        public string $set,
        public array $manifest,
        public Provenance $author,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public ?WriteMode $mode = null,
    ) {}
}
