<?php

namespace LaRecipe\Domains\Upgrade\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;

/**
 * Collects the internal links of converted pages while they render, so the
 * upgrade's validation can check every target page and anchor exists, before
 * anything is written.
 */
final class UpgradeLinkResolver implements LinkResolver
{
    /** @var list<array{from: string, path: string, anchor: ?string, href: string}> */
    public array $links = [];

    /** @param array<string, string> $files page path => file inside the version, so links resolve from the file's folder */
    public function __construct(
        private readonly string $prefix,
        private readonly array $files = [],
    ) {}

    public function page(PageRef $from, string $target): ?string
    {
        if (preg_match('/^([^?#]*?)\.md(?:#(.*))?$/i', $target, $matches)) {
            $path = str_starts_with($matches[1], '/') ? ltrim($matches[1], '/') : $this->join($this->directory($from), $matches[1]);
            $this->record($from, $path, $matches[2] ?? null, $target);

            return '#';
        }

        $docs = '~^/'.preg_quote($this->prefix, '~').'/'.preg_quote($from->version, '~').'(?:/([^?#]*))?(?:#(.*))?$~';

        if ($this->prefix !== '' && preg_match($docs, $target, $matches)) {
            $this->record($from, trim($matches[1] ?? '', '/'), $matches[2] ?? null, $target);
        }

        return null;
    }

    public function asset(PageRef $from, string $target): ?string
    {
        return null;
    }

    private function record(PageRef $from, string $path, ?string $anchor, string $href): void
    {
        $this->links[] = [
            'from' => $from->path,
            'path' => PageRef::normalizePath($path),
            'anchor' => $anchor !== null && $anchor !== '' ? $anchor : null,
            'href' => $href,
        ];
    }

    private function directory(PageRef $ref): string
    {
        $directory = dirname($this->files[$ref->path] ?? $ref->path.'.md');

        return $directory === '.' ? '' : $directory;
    }

    private function join(string $directory, string $relative): string
    {
        $parts = [];

        foreach (explode('/', trim($directory.'/'.$relative, '/')) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }
}
