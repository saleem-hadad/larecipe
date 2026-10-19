<?php

namespace LaRecipe\Domains\Delivery\Services;

use Illuminate\Contracts\Routing\UrlGenerator;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Every URL LaRecipe hands out. One place decides the shape:
 *
 *   /{prefix}[/{locale}][/{version}][/{path}][.md]
 *
 * The locale segment appears only for non-default locales, the version segment
 * only for versioned sets, the path only for pages other than the index.
 */
final class DocsUrls
{
    public function __construct(private readonly UrlGenerator $url) {}

    /** The path of a page relative to the domain, starting with "/". */
    public function pagePath(DocSet $set, PageRef $ref, bool $markdown = false): string
    {
        $segments = $this->baseSegments($set, $ref->locale, $ref->version);

        if ($markdown) {
            $segments[] = $ref->isIndex() ? 'index.md' : $ref->path.'.md';
        } elseif (! $ref->isIndex()) {
            $segments[] = $ref->path;
        }

        return '/'.implode('/', array_filter($segments, fn ($s) => $s !== ''));
    }

    public function page(DocSet $set, PageRef $ref, bool $markdown = false, ?string $anchor = null): string
    {
        return $this->absolute($set, $this->pagePath($set, $ref, $markdown)).($anchor !== null && $anchor !== '' ? '#'.$anchor : '');
    }

    /** The set's root URL (its landing page). */
    public function base(DocSet $set): string
    {
        return $this->absolute($set, '/'.$set->config->prefix);
    }

    /** A path inside the set: "llms.txt", "mcp", "skill.md". */
    public function setPath(DocSet $set, string $relative): string
    {
        return $this->absolute($set, '/'.trim($set->config->prefix.'/'.ltrim($relative, '/'), '/'));
    }

    public function versionPath(DocSet $set, string $version, string $relative, ?string $locale = null): string
    {
        $segments = $this->baseSegments($set, $locale ?? $set->defaultLocale()->code, $version);
        $segments[] = ltrim($relative, '/');

        return $this->absolute($set, '/'.implode('/', array_filter($segments, fn ($s) => $s !== '')));
    }

    public function llms(DocSet $set, ?string $version = null): string
    {
        return $version === null || ! $set->isVersioned()
            ? $this->setPath($set, 'llms.txt')
            : $this->versionPath($set, $version, 'llms.txt');
    }

    public function llmsFull(DocSet $set, string $version): string
    {
        return $set->isVersioned() ? $this->versionPath($set, $version, 'llms-full.txt') : $this->setPath($set, 'llms-full.txt');
    }

    public function mcp(DocSet $set): string
    {
        return $this->setPath($set, (string) $set->config->setting('mcp.web.path', 'mcp'));
    }

    public function skill(DocSet $set): string
    {
        return $this->setPath($set, 'skill.md');
    }

    public function asset(DocSet $set, string $version, string $path): string
    {
        return $this->setPath($set, '_assets/'.$version.'/'.ltrim($path, '/'));
    }

    /** The domain-relative path of a docs asset, for cached article HTML. */
    public function assetPath(DocSet $set, string $version, string $path): string
    {
        return '/'.ltrim(preg_replace('#/+#', '/', $set->config->prefix.'/_assets/'.$version.'/'.ltrim($path, '/')) ?? '', '/');
    }

    /** @return list<string> */
    private function baseSegments(DocSet $set, string $locale, string $version): array
    {
        $segments = [$set->config->prefix];

        if ($set->isMultilingual() && $locale !== $set->defaultLocale()->code) {
            $segments[] = $locale;
        }

        if ($set->isVersioned()) {
            $segments[] = $version;
        }

        return $segments;
    }

    private function absolute(DocSet $set, string $path): string
    {
        $path = '/'.ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');

        if ($set->config->domain !== null) {
            $scheme = parse_url($this->url->to('/'), PHP_URL_SCHEME) ?: 'https';

            return rtrim($scheme.'://'.$set->config->domain.$path, '/') ?: '/';
        }

        $url = $this->url->to($path);

        return $path === '/' ? rtrim($url, '/').'/' : $url;
    }
}
