<?php

namespace LaRecipe\Domains\Delivery\Support;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;

/**
 * Resolves the links authors write (relative .md files, so docs read well on
 * GitHub too) into reader URLs, relative to the page that contains them.
 *
 * It returns domain-relative paths, because rendered articles are cached and
 * shared across hosts; agent Markdown makes them absolute.
 */
final class DocsLinkResolver implements LinkResolver
{
    public function __construct(
        private readonly DocSet $set,
        private readonly DocsUrls $urls,
        private readonly ContentSource $source,
        private readonly bool $markdownTwins = false,
    ) {}

    public function page(PageRef $from, string $target): ?string
    {
        if (! preg_match('/^([^?#]*?)\.md(#.*)?$/i', $target, $matches)) {
            return null;
        }

        $anchor = isset($matches[2]) ? ltrim($matches[2], '#') : null;
        $path = str_starts_with($matches[1], '/')
            ? ltrim($matches[1], '/')
            : $this->join($this->directoryOf($from), $matches[1]);

        // In a .md twin, page links point at the other pages' .md twins, so agents stay in Markdown.
        $url = $this->urls->pagePath($this->set, $from->withPath($path), $this->markdownTwins);

        return $anchor !== null && $anchor !== '' ? $url.'#'.$anchor : $url;
    }

    public function asset(PageRef $from, string $target): ?string
    {
        $clean = preg_replace('/[?#].*$/', '', $target) ?? $target;

        if (str_ends_with(strtolower($clean), '.md')) {
            return null;
        }

        $path = str_starts_with($clean, '/') ? ltrim($clean, '/') : $this->join($this->directoryOf($from), $clean);
        $path = preg_replace('#^_assets/#', '', $path) ?? $path;

        return $this->urls->assetPath($this->set, $from->version, $path);
    }

    private function directoryOf(PageRef $ref): string
    {
        if ($ref->isIndex()) {
            return '';
        }

        if ($this->source instanceof RepositorySource) {
            $file = $this->source->pageFile($ref);

            if ($file !== null && str_ends_with(str_replace('\\', '/', $file), '/'.$ref->path.'/index.md')) {
                return $ref->path;
            }
        }

        $directory = dirname($ref->path);

        return $directory === '.' ? '' : $directory;
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
