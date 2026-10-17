<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * Move a set from one driver to the other: `repository` to `database`, or back.
 * The options are each driver's source config (path, connection, cache, disk).
 */
final readonly class SourceMoveRequest
{
    /**
     * @param  array<string, mixed>  $fromOptions
     * @param  array<string, mixed>  $toOptions
     */
    public function __construct(
        public string $set,
        public string $from,
        public string $to,
        public array $fromOptions = [],
        public array $toOptions = [],
        public bool $dryRun = false,
        public bool $verify = false,
    ) {}
}
