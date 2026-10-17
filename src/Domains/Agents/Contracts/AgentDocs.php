<?php

namespace LaRecipe\Domains\Agents\Contracts;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\PageSummary;

/**
 * What every agent surface (llms.txt, skill.md, MCP, Ask AI) needs to read a
 * doc set, already filtered for the caller.
 *
 * Every method answers through the one access policy, so a page that is hidden
 * from the caller is simply absent here (the Same-Rules Rule). The Laravel
 * adapter backs it with the page catalog, the access policy and the agent
 * Markdown renderer; a standalone CLI would bring its own.
 */
interface AgentDocs
{
    /** @return list<DocSet> the sets the caller may enter */
    public function sets(): array;

    /** The set with this key, or null when it does not exist or is hidden from the caller. */
    public function set(string $key): ?DocSet;

    public function defaultSet(): DocSet;

    public function canViewVersion(DocSet $set, string $version): bool;

    /** @return array<string, PageSummary> the caller's visible pages, keyed by path */
    public function pages(DocSet $set, string $version, string $locale): array;

    /**
     * Navigation as the caller sees it: tabs of page, group and link items,
     * the same arrays the reader UI receives.
     *
     * @return list<array{label: ?string, href: ?string, icon: ?string, items: list<array>}>
     */
    public function navigation(DocSet $set, string $version, string $locale): array;

    /** The page when it exists and the caller may see it. */
    public function page(DocSet $set, PageRef $ref): ?Page;

    /**
     * The page's agent Markdown, the same projection served at its .md URL.
     * Without hints, the one-line pointer to llms.txt, MCP and skill.md is left
     * out (llms-full.txt has one at the top instead of one per page).
     */
    public function markdown(DocSet $set, Page $page, bool $hints = true): string;

    /** Changes whenever the version's content changes. */
    public function fingerprint(DocSet $set, string $version): string;

    /** True when every caller would get the same output (a public set and a guest), so it may be cached and shared. */
    public function isSharedView(DocSet $set): bool;

    /** The folder holding the set's hand-written skills (skills/{name}/SKILL.md), or null when the source has none. */
    public function skillsDirectory(DocSet $set): ?string;
}
