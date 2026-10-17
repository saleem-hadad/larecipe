<?php

namespace LaRecipe\Domains\Agents\Contracts;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * The absolute URLs agent surfaces hand out. One implementation decides their
 * shape, so llms.txt, skill.md, MCP results and Ask AI citations always point
 * at the same addresses the reader UI uses.
 */
interface DocsLinks
{
    public function page(DocSet $set, PageRef $ref, bool $markdown = false): string;

    public function base(DocSet $set): string;

    /** llms.txt: the set root when no version, locale or group is given; otherwise path-scoped. */
    public function llms(DocSet $set, ?string $version = null, ?string $locale = null, ?string $group = null): string;

    public function llmsFull(DocSet $set, string $version, ?string $locale = null, ?string $group = null): string;

    public function skill(DocSet $set): string;

    public function skillFile(DocSet $set, string $name, string $file = 'SKILL.md'): string;

    /** The set's MCP endpoint, or null when web MCP is turned off for the set. */
    public function mcp(DocSet $set): ?string;

    public function schema(DocSet $set, string $name): string;

    public function sitemap(DocSet $set): string;

    /** A path inside the set ("_ask", "ask") relative to its version and locale. */
    public function versionPath(DocSet $set, string $version, string $relative, ?string $locale = null): string;
}
