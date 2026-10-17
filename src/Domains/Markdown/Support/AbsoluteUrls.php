<?php

namespace LaRecipe\Domains\Markdown\Support;

/**
 * Makes link and image targets absolute against the page's URL, so a `.md`
 * twin still works when an agent reads it out of context (pasted into a chat,
 * saved to disk, fetched through a proxy).
 *
 * Domain-relative targets (what link resolution produces) get the origin;
 * relative ones are resolved like a browser would (RFC 3986).
 */
final class AbsoluteUrls
{
    private readonly string $origin;

    private readonly string $path;

    /**
     * @param  string  $page  the absolute URL of the page being serialized
     * @param  string|null  $base  the origin for domain-relative links (defaults to the page's)
     * @param  bool  $isFolder  the page is a folder's index (/docs serves docs/index.md), so relative links start inside it
     */
    public function __construct(private readonly string $page, ?string $base = null, bool $isFolder = false)
    {
        $parts = parse_url($page) ?: [];
        $origin = isset($parts['host'])
            ? ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';

        $this->origin = rtrim($base ?? $origin, '/');
        $this->path = ($parts['path'] ?? '/').($isFolder && ! str_ends_with($parts['path'] ?? '/', '/') ? '/' : '');
    }

    public function __invoke(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return (parse_url($this->origin, PHP_URL_SCHEME) ?: 'https').':'.$url;
        }

        if (str_starts_with($url, '#')) {
            return strtok($this->page, '#').$url;
        }

        if (str_starts_with($url, '/')) {
            return $this->origin.$url;
        }

        if (str_starts_with($url, '?')) {
            return strtok(strtok($this->page, '#'), '?').$url;
        }

        $directory = substr($this->path, 0, (int) strrpos($this->path, '/') + 1);

        return $this->origin.$this->removeDotSegments($directory.$url);
    }

    private function removeDotSegments(string $url): string
    {
        $suffix = '';

        if (preg_match('/^([^?#]*)([?#].*)$/', $url, $matches)) {
            [$url, $suffix] = [$matches[1], $matches[2]];
        }

        $segments = [];

        foreach (explode('/', $url) as $segment) {
            if ($segment === '..') {
                if (count($segments) > 1) {
                    array_pop($segments);
                }
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments).$suffix;
    }
}
