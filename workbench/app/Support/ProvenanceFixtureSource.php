<?php

namespace Workbench\App\Support;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;

/**
 * The repository source with provenance read from a fixture file
 * (_provenance.json), the way the git resolver will provide it in a real app.
 *
 * Times are relative ("-2 hours") so the fresh dot and "Updated 2 hours ago"
 * stay true whenever the workbench runs, which keeps screenshots and browser
 * tests stable.
 */
class ProvenanceFixtureSource extends RepositorySource
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $entries = null;

    public function page(PageRef $ref): ?Page
    {
        $page = parent::page($ref);
        $entry = $this->entry($ref);

        if ($page === null || $entry === null) {
            return $page;
        }

        // Relative to the start of the hour, so every request in a run dates a
        // page the same way (the browser remembers opened pages by that date).
        $at = (new DateTimeImmutable(date('Y-m-d H:00:00')))->modify((string) $entry['updated']);
        $provenance = isset($entry['name'])
            ? new Provenance(
                Actor::tryFrom((string) ($entry['actor'] ?? 'human')) ?? Actor::Human,
                (string) $entry['name'],
                isset($entry['ref']) ? (string) $entry['ref'] : null,
                $at,
                isset($entry['approved_by']) ? (string) $entry['approved_by'] : null,
            )
            : null;

        return $page->withProvenance($provenance, $at);
    }

    /**
     * The fixture entry for a page, keyed "{version}/{locale}/{path}".
     *
     * @return array<string, mixed>|null
     */
    public function entry(PageRef $ref): ?array
    {
        if ($this->entries === null) {
            $file = $this->root().'/_provenance.json';
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->entries = is_array($data) ? $data : [];
        }

        return $this->entries["{$ref->version}/{$ref->locale}/{$ref->path}"] ?? null;
    }
}
