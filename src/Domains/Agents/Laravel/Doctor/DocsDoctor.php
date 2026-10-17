<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\Actions\BuildOutlineAction;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\DTOs\DoctorReport;
use LaRecipe\Domains\Agents\DTOs\IndexEntry;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Grades a set's agent readiness the way AFDocs grades a docs site, from
 * inside the app: Markdown parity, llms.txt size and links, 404 behavior,
 * discovery headers, redirects, skill.md and MCP, plus LaRecipe's own cache
 * store check. Every finding comes with a fix.
 */
final class DocsDoctor
{
    private const SAMPLES = 5;

    public function __construct(
        private readonly DocsProbe $probe,
        private readonly DocsLinks $links,
        private readonly BuildOutlineAction $outline,
        private readonly CacheStoreCheck $cacheStore,
    ) {}

    public function examine(DocSet $set): DoctorReport
    {
        $context = new DoctorContext($set, $this->probe, $this->links, $this->samples($set));

        $checks = [
            new LlmsTxtCheck,
            new LlmsLinksCheck,
            new MarkdownCheck,
            new NotFoundCheck,
            new DiscoveryCheck,
            new RedirectsCheck,
            new SkillCheck,
            new McpCheck,
            $this->cacheStore,
        ];

        return new DoctorReport($set->key, $this->links->base($set), array_map(fn (Check $check) => $check->run($context), $checks));
    }

    /** @return list<PageRef> the first public pages in reading order */
    private function samples(DocSet $set): array
    {
        $version = $set->defaultVersion()->id;
        $locale = $set->defaultLocale()->code;
        $entries = array_slice($this->outline->execute($set, $version, $locale)->entries(), 0, self::SAMPLES);

        return array_map(fn (IndexEntry $entry) => new PageRef($set->key, $version, $locale, (string) $entry->path), $entries);
    }
}
