<?php

namespace LaRecipe\Domains\Search\Laravel;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Search\DTOs\Audience;

/**
 * The URLs search hands out, built by the same DocsUrls as every page URL:
 *
 *   /{prefix}[/{locale}][/{version}]/_search/{audience}.{fingerprint}.json   client index
 *   /{prefix}[/{locale}][/{version}]/_search?q=                              server search
 *
 * Index and endpoint URLs are domain-relative, like the other URLs the UI follows.
 */
final class SearchUrls
{
    public function __construct(private readonly DocsUrls $urls) {}

    public function index(DocSet $set, string $version, string $locale, Audience $audience): string
    {
        return $this->path($set, $version, $locale, '_search/'.$audience->fileName());
    }

    public function endpoint(DocSet $set, string $version, string $locale): string
    {
        return $this->path($set, $version, $locale, '_search');
    }

    /** Where the palette reports the searches it answered in the browser. */
    public function report(DocSet $set, string $version, string $locale): string
    {
        return $this->path($set, $version, $locale, '_search/report');
    }

    /** A page section as the reader opens it (domain-relative, for client navigation). */
    public function section(DocSet $set, string $version, string $locale, string $path, ?string $anchor): string
    {
        $url = $this->urls->pagePath($set, new PageRef($set->key, $version, $locale, $path));

        return $anchor !== null && $anchor !== '' ? $url.'#'.$anchor : $url;
    }

    /** An absolute page URL, for agents that read hits out of context. */
    public function absolute(PageRef $ref, DocSet $set, ?string $anchor = null, bool $markdown = false): string
    {
        return $this->urls->page($set, $ref, $markdown, $markdown ? null : $anchor);
    }

    private function path(DocSet $set, string $version, string $locale, string $relative): string
    {
        return (string) parse_url($this->urls->versionPath($set, $version, $relative, $locale), PHP_URL_PATH);
    }
}
