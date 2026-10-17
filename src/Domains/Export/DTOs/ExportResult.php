<?php

namespace LaRecipe\Domains\Export\DTOs;

/**
 * What an export wrote, per set, and what it left out on purpose.
 */
final readonly class ExportResult
{
    /**
     * @param  list<string>  $files  written files, relative to the output folder
     * @param  array<string, array{pages: int, markdown: int, private: int}>  $sets
     * @param  list<string>  $leftOut  one sentence per thing a static site cannot have
     */
    public function __construct(
        public array $files,
        public array $sets,
        public array $leftOut,
    ) {}
}
