<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\Enums\Visibility;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Sources\Actions\GetPageAction;

/**
 * GET /{prefix}/sitemap.xml: every public page of a public set, with hreflang
 * alternates for its translations.
 *
 * A sitemap is a public artifact, so it lists only what a guest may see, never
 * what the current caller happens to be allowed: private sets answer 404, and
 * private versions, private pages, hidden pages and noindex pages are left out.
 */
final class SitemapController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly DocsUrls $urls,
        private readonly GetPageAction $pages,
        private readonly Cache $cache,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->current->set($request);

        if (! $this->access->isPublic($set) || ! $set->config->setting('agents.sitemap', true)) {
            abort(404);
        }

        $fingerprint = implode('|', array_map(fn ($version) => $this->catalog->fingerprint($set, $version->id), $set->versions()));
        $key = 'larecipe:'.$set->key.':sitemap:'.hash('xxh128', $fingerprint.'|'.$this->urls->base($set));

        $xml = $this->cache->rememberForever($key, fn () => $this->build($set));

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600, s-maxage=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function build(DocSet $set): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ];

        foreach ($set->versions() as $version) {
            if ($version->visibility === Visibility::Private) {
                continue;
            }

            foreach ($this->translations($set, $version->id) as $locales) {
                $alternates = count($locales) > 1 ? $this->alternates($set, $locales) : [];

                foreach ($locales as $summary) {
                    $lines[] = '  <url>';
                    $lines[] = '    <loc>'.$this->xml($this->urls->page($set, $summary->ref)).'</loc>';

                    if ($summary->updatedAt !== null) {
                        $lines[] = '    <lastmod>'.$summary->updatedAt->format('Y-m-d').'</lastmod>';
                    }

                    array_push($lines, ...$alternates);
                    $lines[] = '  </url>';
                }
            }
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * The version's public pages, grouped by path across locales.
     *
     * @return array<string, array<string, PageSummary>> path => locale code => page
     */
    private function translations(DocSet $set, string $version): array
    {
        $pages = [];

        foreach ($set->locales() as $locale) {
            foreach ($this->catalog->summaries($set, $version, $locale->code) as $path => $summary) {
                if ($summary->private || $summary->hidden || $this->pages->execute($summary->ref)?->frontmatter->noindex() !== false) {
                    continue;
                }

                $pages[$path][$locale->code] = $summary;
            }
        }

        return $pages;
    }

    /**
     * @param  array<string, PageSummary>  $locales
     * @return list<string>
     */
    private function alternates(DocSet $set, array $locales): array
    {
        $lines = [];

        foreach ($locales as $code => $summary) {
            $lines[] = '    <xhtml:link rel="alternate" hreflang="'.$this->xml($code).'" href="'.$this->xml($this->urls->page($set, $summary->ref)).'"/>';
        }

        $default = $locales[$set->defaultLocale()->code] ?? null;

        if ($default !== null) {
            $lines[] = '    <xhtml:link rel="alternate" hreflang="x-default" href="'.$this->xml($this->urls->page($set, $default->ref)).'"/>';
        }

        return $lines;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
