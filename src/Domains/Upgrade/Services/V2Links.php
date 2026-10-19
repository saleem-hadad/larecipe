<?php

namespace LaRecipe\Domains\Upgrade\Services;

/**
 * Understands the links v2 docs were written with, and turns them into v3 links.
 *
 *   /{{route}}/{{version}}/page#anchor   always becomes a relative link: page.md#anchor
 *   /docs/{{version}}/page               kept (v3 still fills in {{version}}), or made
 *                                        relative with --relative-links
 *
 * Relative .md links read well on GitHub and in any editor, and v3 resolves them
 * to reader URLs, so they are the format docs should end up in.
 */
final class V2Links
{
    private const ROUTE = '#^/?\{\{\s*route\s*\}\}(?:/\{\{\s*version\s*\}\})?/?(.*)$#';

    /**
     * @param  string  $prefix  the v2 route prefix without slashes ("docs")
     * @param  string  $landing  the v2 landing page, for links to a version's root
     */
    public function __construct(
        private readonly string $prefix,
        private readonly bool $relative,
        private readonly string $landing = 'overview',
    ) {}

    /**
     * The page a v2 docs link points to, or null when it is not a link into the docs.
     *
     * @return array{path: string, anchor: ?string, placeholder: bool}|null
     */
    public function target(string $url, string $version): ?array
    {
        $url = trim($url);

        if (preg_match(self::ROUTE, $url, $matches)) {
            return $this->split($matches[1], true);
        }

        $prefix = preg_quote($this->prefix, '#');

        if ($this->prefix !== '' && preg_match('#^/'.$prefix.'/(?:\{\{\s*version\s*\}\}|'.preg_quote($version, '#').')(?:/(.*))?$#', $url, $matches)) {
            return $this->split($matches[1] ?? '', false);
        }

        return null;
    }

    /**
     * The v3 form of a link found on a page, or null to leave it as written.
     *
     * @param  string  $fromDirectory  the linking page's folder inside the version ("" at the root)
     * @param  array<string, string>  $files  page path => file inside the version ("overview" => "overview.md")
     */
    public function rewrite(string $url, string $version, string $fromDirectory, array $files): ?string
    {
        $target = $this->target($url, $version);

        if ($target === null || (! $target['placeholder'] && ! $this->relative)) {
            return null;
        }

        $file = $files[$target['path']] ?? $target['path'].'.md';

        return $this->relativePath($fromDirectory, $file).($target['anchor'] !== null ? '#'.$target['anchor'] : '');
    }

    /** The page path inside the version a sidebar link points to, or null for an outside link. */
    public function pagePath(string $url, string $version): ?string
    {
        return $this->target($url, $version)['path'] ?? null;
    }

    /** @return array{path: string, anchor: ?string, placeholder: bool} */
    private function split(string $rest, bool $placeholder): array
    {
        [$path, $anchor] = array_pad(explode('#', $rest, 2), 2, null);
        $path = trim((string) preg_replace('/[?].*$/', '', (string) $path), '/');
        $path = (string) preg_replace('/\.md$/i', '', $path);

        return ['path' => $path === '' ? $this->landing : $path, 'anchor' => $anchor !== null && $anchor !== '' ? $anchor : null, 'placeholder' => $placeholder];
    }

    private function relativePath(string $fromDirectory, string $file): string
    {
        $from = $fromDirectory === '' ? [] : explode('/', trim($fromDirectory, '/'));
        $to = explode('/', $file);

        while ($from !== [] && count($to) > 1 && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)).implode('/', $to);
    }
}
