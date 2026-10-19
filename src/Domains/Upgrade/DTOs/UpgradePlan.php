<?php

namespace LaRecipe\Domains\Upgrade\DTOs;

/**
 * Everything `larecipe:upgrade` would do, worked out without touching the disk:
 * the files it writes, moves and removes, the converted pages, the generated
 * config and manifest, and every entry of the report.
 */
final readonly class UpgradePlan
{
    /**
     * @param  list<PlannedFile>  $files
     * @param  list<ConvertedPage>  $pages
     * @param  array<string, mixed>  $manifest  the new larecipe.json
     * @param  array<string, array>  $navigation  version => navigation.json
     * @param  list<ReportEntry>  $entries
     * @param  list<string>  $composer  commands to run, in order
     */
    public function __construct(
        public V2Project $project,
        public array $files,
        public array $pages,
        public array $manifest,
        public array $navigation,
        public string $config,
        public array $entries,
        public array $composer = [],
    ) {}

    /** @return list<ReportEntry> */
    public function entries(string $kind): array
    {
        return array_values(array_filter($this->entries, fn (ReportEntry $entry) => $entry->kind === $kind));
    }
}
