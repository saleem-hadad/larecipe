<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Where a link in a page points, worked out the same way the delivery link
 * resolver does it: relative .md links from the page's folder, absolute links
 * from the set's prefix.
 */
final class LinkPaths
{
    /**
     * A relative .md link ("../billing.md#retries") from a page.
     *
     * @return array{path: string, anchor: ?string}|null
     */
    public function relative(string $from, bool $folderIndex, string $href): ?array
    {
        if (! preg_match('/^([^?#]*?)\.md(?:[?][^#]*)?(?:#(.*))?$/i', $href, $matches)) {
            return null;
        }

        $target = rawurldecode($matches[1]);
        $directory = $from === 'index' ? '' : ($folderIndex ? $from : (dirname($from) === '.' ? '' : dirname($from)));
        $path = str_starts_with($target, '/') ? ltrim($target, '/') : $this->join($directory, $target);

        return ['path' => PageRef::normalizePath($path), 'anchor' => $this->anchor($matches[2] ?? null)];
    }

    /**
     * An absolute link into the set ("/docs/2.x/billing#retries"), given the
     * set's prefix and the URL segments of the page's version ("2.x", "ar/2.x").
     * Null when the link points somewhere else.
     *
     * @return array{path: string, anchor: ?string}|null
     */
    public function absolute(string $prefix, string $label, string $href): ?array
    {
        if (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
            return null;
        }

        [$path, $anchor] = array_pad(explode('#', (string) preg_replace('/\?[^#]*/', '', $href), 2), 2, null);
        $base = '/'.implode('/', array_filter(explode('/', trim($prefix, '/').'/'.$label), fn (string $s) => $s !== ''));
        $path = '/'.trim(rawurldecode((string) $path), '/');

        if ($path === $base) {
            return ['path' => 'index', 'anchor' => $this->anchor($anchor)];
        }

        $start = $base === '/' ? '/' : $base.'/';

        if (! str_starts_with($path, $start)) {
            return null;
        }

        $relative = substr($path, strlen($start));

        if (preg_match('/^(_assets|larecipe-assets|skills|schemas)\//', $relative) || preg_match('/\.(txt|xml|json|png|jpe?g|gif|svg|webp|pdf)$/i', $relative)) {
            return null;
        }

        return ['path' => PageRef::normalizePath($relative), 'anchor' => $this->anchor($anchor)];
    }

    /**
     * Every id on a rendered page: heading ids, explicit {#ids}, footnotes.
     *
     * @return list<string>
     */
    public static function anchorsIn(string $html): array
    {
        preg_match_all('/\sid="([^"]+)"/', $html, $matches);

        return array_values(array_unique(array_map(fn (string $id) => html_entity_decode($id, ENT_QUOTES), $matches[1])));
    }

    private function anchor(?string $anchor): ?string
    {
        return $anchor === null || $anchor === '' ? null : rawurldecode($anchor);
    }

    private function join(string $directory, string $relative): string
    {
        $parts = [];

        foreach (explode('/', trim($directory.'/'.$relative, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
