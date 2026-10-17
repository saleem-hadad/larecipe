<?php

namespace LaRecipe\Domains\Agents\Laravel;

use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Services\DocsUrls;

/**
 * Agent URLs built by Delivery's DocsUrls, the one place that decides what a
 * LaRecipe URL looks like. Agents never build URLs by hand.
 */
final class DeliveryDocsLinks implements DocsLinks
{
    public function __construct(private readonly DocsUrls $urls) {}

    public function page(DocSet $set, PageRef $ref, bool $markdown = false): string
    {
        return $this->urls->page($set, $ref, $markdown);
    }

    public function base(DocSet $set): string
    {
        return $this->urls->base($set);
    }

    public function llms(DocSet $set, ?string $version = null, ?string $locale = null, ?string $group = null): string
    {
        if ($version === null && $locale === null && $group === null) {
            return $this->urls->setPath($set, 'llms.txt');
        }

        $relative = ($group !== null ? trim($group, '/').'/' : '').'llms.txt';

        if ($version === null && $set->isVersioned()) {
            return $this->localeRoot($set, $locale, $relative);
        }

        return $this->urls->versionPath($set, $version ?? $set->defaultVersion()->id, $relative, $locale);
    }

    public function llmsFull(DocSet $set, string $version, ?string $locale = null, ?string $group = null): string
    {
        return $this->urls->versionPath($set, $version, ($group !== null ? trim($group, '/').'/' : '').'llms-full.txt', $locale);
    }

    public function skill(DocSet $set): string
    {
        return $this->urls->skill($set);
    }

    public function skillFile(DocSet $set, string $name, string $file = 'SKILL.md'): string
    {
        return $this->urls->setPath($set, 'skills/'.$name.'/'.ltrim($file, '/'));
    }

    public function mcp(DocSet $set): ?string
    {
        return $set->config->setting('mcp.web.enabled', true) ? $this->urls->mcp($set) : null;
    }

    public function schema(DocSet $set, string $name): string
    {
        return $this->urls->setPath($set, 'schemas/'.$name.'.json');
    }

    public function sitemap(DocSet $set): string
    {
        return $this->urls->setPath($set, 'sitemap.xml');
    }

    public function versionPath(DocSet $set, string $version, string $relative, ?string $locale = null): string
    {
        return $this->urls->versionPath($set, $version, $relative, $locale);
    }

    /** A locale's root of a versioned set: /{prefix}/{locale}/llms.txt. */
    private function localeRoot(DocSet $set, ?string $locale, string $relative): string
    {
        if ($locale === null || $locale === $set->defaultLocale()->code) {
            return $this->urls->setPath($set, $relative);
        }

        return $this->urls->setPath($set, $locale.'/'.$relative);
    }
}
