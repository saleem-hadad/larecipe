<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\Enums\VersionStatus;

/**
 * The words agent surfaces use to introduce a doc set: its title, the one-line
 * summary, the owner's agent instructions and the "how to read these docs"
 * notes. Shared so llms.txt, llms-full.txt and skill.md say the same thing.
 */
final class SetDescriber
{
    public function __construct(
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
    ) {}

    public function title(DocSet $set, ?string $version = null, ?string $locale = null): string
    {
        $title = $set->manifest->name;

        if ($version !== null && $set->isVersioned()) {
            $title .= ' '.($set->version($version)?->label ?? $version);
        }

        if ($locale !== null && $set->isMultilingual() && $locale !== $set->defaultLocale()->code) {
            $title .= ' ('.($set->locale($locale)?->label ?? $locale).')';
        }

        return $title;
    }

    /** agents.llmsTxt.description, else the manifest description, else a plain fallback. */
    public function summary(DocSet $set): string
    {
        $summary = $set->manifest->get('agents.llmsTxt.description') ?? $set->manifest->description;

        return is_string($summary) && trim($summary) !== '' ? trim($summary) : "The documentation for {$set->manifest->name}.";
    }

    /** The owner's instructions for agents (agents.llmsTxt.instructions), if any. */
    public function instructions(DocSet $set): ?string
    {
        $instructions = $set->manifest->get('agents.llmsTxt.instructions');

        return is_string($instructions) && trim($instructions) !== '' ? trim($instructions) : null;
    }

    /**
     * How an agent reaches the rest of the set from an index. URLs are written
     * as code, not links, so index link checks only see pages.
     *
     * @return list<string>
     */
    public function accessNotes(DocSet $set, string $version, string $locale): array
    {
        $notes = ['Every page is also available as Markdown: the links below point to it, and adding `.md` to any page URL works too.'];
        $mcp = $this->links->mcp($set);

        if ($mcp !== null) {
            $notes[] = "Search and read these docs with the MCP server at `{$mcp}` (tools: search_docs, read_page, list_pages).";
        }

        if ($set->config->setting('agents.llms_full', true)) {
            $notes[] = 'The full text of this version is at `'.$this->links->llmsFull($set, $version, $this->localeArgument($set, $locale)).'`.';
        }

        if ($set->config->setting('agents.skill_md', true)) {
            $notes[] = 'An agent skill for these docs is at `'.$this->links->skill($set).'`.';
        }

        $others = $this->otherVersions($set, $version, $locale);

        if ($others !== []) {
            $notes[] = 'Other versions: '.implode(', ', $others).'.';
        }

        return [implode(' ', $notes)];
    }

    /** @return list<string> */
    private function otherVersions(DocSet $set, string $version, string $locale): array
    {
        if (! $set->isVersioned()) {
            return [];
        }

        $others = [];

        foreach ($set->versions() as $candidate) {
            if ($candidate->id !== $version && $this->docs->canViewVersion($set, $candidate->id)) {
                $status = $candidate->status === VersionStatus::Current ? '' : ' ('.$candidate->status->value.')';
                $others[] = $candidate->label.$status.' at `'.$this->links->llms($set, $candidate->id, $this->localeArgument($set, $locale)).'`';
            }
        }

        return $others;
    }

    private function localeArgument(DocSet $set, string $locale): ?string
    {
        return $locale === $set->defaultLocale()->code ? null : $locale;
    }
}
