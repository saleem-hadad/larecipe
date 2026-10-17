<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * A hand-written skill from the set's skills/ folder, as written.
 */
final readonly class SkillSnapshot
{
    public function __construct(
        public string $folder,
        public string $file,
        public string $contents,
    ) {}
}
