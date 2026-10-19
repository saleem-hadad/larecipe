<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Support\SafeHref;
use LaRecipe\Support\Arr;

/**
 * A doc set's larecipe.json: presentation and content structure.
 *
 * Infrastructure (routes, driver, cache, AI) never lives here; it is in
 * config/larecipe.php. Unknown keys are preserved in $raw so a round trip
 * through any source never loses data.
 */
final readonly class Manifest
{
    /**
     * @param  list<Version>  $versions
     * @param  list<Locale>  $locales
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public Theme $theme,
        public array $versions,
        public array $locales,
        public ?string $landing = null,
        public array $raw = [],
    ) {}

    public static function fromArray(array $data, string $fallbackName = 'Docs'): self
    {
        $versions = array_values(array_map(
            fn ($version) => Version::fromArray(is_array($version) ? $version : ['id' => (string) $version]),
            (array) ($data['versions'] ?? []),
        ));

        if ($versions !== [] && ! array_filter($versions, fn (Version $v) => $v->default)) {
            $first = $versions[0];
            $versions[0] = new Version($first->id, $first->label, $first->status, true, $first->visibility);
        }

        $locales = array_values(array_map(
            fn ($locale) => Locale::fromArray(is_array($locale) ? $locale : ['code' => (string) $locale]),
            (array) ($data['locales'] ?? []),
        ));

        if ($locales !== [] && ! array_filter($locales, fn (Locale $l) => $l->default)) {
            $first = $locales[0];
            $locales[0] = new Locale($first->code, $first->label, $first->dir, true);
        }

        return new self(
            name: (string) ($data['name'] ?? $fallbackName),
            description: isset($data['description']) ? (string) $data['description'] : null,
            theme: Theme::fromArray((array) ($data['theme'] ?? [])),
            versions: $versions,
            locales: $locales,
            landing: isset($data['landing']) ? (string) $data['landing'] : null,
            raw: $data,
        );
    }

    public static function empty(string $name = 'Docs'): self
    {
        return self::fromArray([], $name);
    }

    public function isVersioned(): bool
    {
        return $this->versions !== [];
    }

    public function isMultilingual(): bool
    {
        return count($this->locales) > 1;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->raw, $key, $default);
    }

    /** @return array{light: ?string, dark: ?string} */
    public function logo(): array
    {
        $logo = $this->raw['logo'] ?? null;

        if (is_string($logo)) {
            return ['light' => $logo, 'dark' => $logo];
        }

        return ['light' => $logo['light'] ?? null, 'dark' => $logo['dark'] ?? ($logo['light'] ?? null)];
    }

    public function favicon(): ?string
    {
        return isset($this->raw['favicon']) ? (string) $this->raw['favicon'] : null;
    }

    /**
     * The navbar's links and call to action, leaving out links a page may not
     * carry (SafeHref) and a link to the repository, which the header already
     * shows as its icon.
     *
     * @return array{links?: list<array>, cta?: array}
     */
    public function navbar(): array
    {
        $navbar = (array) ($this->raw['navbar'] ?? []);

        if (isset($navbar['links'])) {
            $repository = $this->repository()['url'];

            $navbar['links'] = array_values(array_filter(
                self::safeLinks((array) $navbar['links']),
                fn (array $link) => $repository === null || rtrim(trim((string) $link['href']), '/') !== $repository,
            ));
        }

        if (isset($navbar['cta']) && self::safeLinks([$navbar['cta']]) === []) {
            unset($navbar['cta']);
        }

        return $navbar;
    }

    /**
     * The footer's links and text, leaving out links a page may not carry (SafeHref).
     *
     * @return array{links?: list<array>, text?: string}
     */
    public function footer(): array
    {
        $footer = (array) ($this->raw['footer'] ?? []);

        if (isset($footer['links'])) {
            $footer['links'] = self::safeLinks((array) $footer['links']);
        }

        return $footer;
    }

    /** @return list<string> the navbar, footer and repository links that pages leave out */
    public function refusedLinks(): array
    {
        $links = [...(array) ($this->raw['navbar']['links'] ?? []), $this->raw['navbar']['cta'] ?? null, ...(array) ($this->raw['footer']['links'] ?? [])];
        $refused = [];

        foreach ($links as $link) {
            if (is_array($link) && isset($link['href']) && SafeHref::of((string) $link['href']) === null) {
                $refused[] = (string) $link['href'];
            }
        }

        foreach (['url', 'docsUrl'] as $key) {
            $repository = $this->raw['repository'][$key] ?? null;

            if (is_scalar($repository) && ! SafeHref::isWebUrl((string) $repository)) {
                $refused[] = (string) $repository;
            }
        }

        return $refused;
    }

    /**
     * The project's repository (url: the header's icon) and the one the docs
     * files live in (docsUrl: edit and commit links), which is the same
     * repository unless docsUrl names another. branch and docsPath describe
     * the docs repository. URLs only when they are http or https.
     *
     * @return array{url: ?string, docsUrl: ?string, branch: string, docsPath: string}
     */
    public function repository(): array
    {
        $repository = (array) ($this->raw['repository'] ?? []);
        $url = self::webUrl($repository['url'] ?? null);

        return [
            'url' => $url,
            'docsUrl' => array_key_exists('docsUrl', $repository) ? self::webUrl($repository['docsUrl']) : $url,
            'branch' => (string) ($repository['branch'] ?? 'main'),
            'docsPath' => trim((string) ($repository['docsPath'] ?? 'docs'), '/'),
        ];
    }

    private static function webUrl(mixed $url): ?string
    {
        return is_scalar($url) && SafeHref::isWebUrl((string) $url) ? rtrim(trim((string) $url), '/') : null;
    }

    /**
     * The stylesheets listed under assets.css, as written: files next to
     * larecipe.json ("theme.css") that load after LaRecipe's own CSS.
     *
     * @return list<string>
     */
    public function stylesheets(): array
    {
        $css = $this->raw['assets']['css'] ?? [];

        return array_values(array_filter(is_array($css) ? $css : [], fn ($file) => is_string($file) && trim($file) !== ''));
    }

    /** Whether a path inside the docs root is one of the listed stylesheets. */
    public function listsStylesheet(string $path): bool
    {
        return in_array(trim($path, '/'), array_map(fn (string $file) => trim($file, '/'), $this->stylesheets()), true);
    }

    /** @return array<string, list<string>> insert point => component names */
    public function inserts(): array
    {
        return array_map(fn ($names) => array_values((array) $names), (array) ($this->raw['inserts'] ?? []));
    }

    /** @return list<array{from: string, to: string, status: int}> */
    public function redirects(): array
    {
        return array_values(array_map(fn (array $r) => [
            'from' => (string) $r['from'],
            'to' => (string) $r['to'],
            'status' => (int) ($r['status'] ?? 301),
        ], array_filter((array) ($this->raw['redirects'] ?? []), fn ($r) => is_array($r) && isset($r['from'], $r['to']))));
    }

    /** @return list<array> the links (label and href) whose href a page may carry */
    private static function safeLinks(array $links): array
    {
        return array_values(array_filter($links, fn ($link) => is_array($link) && isset($link['href']) && SafeHref::of((string) $link['href']) !== null));
    }

    public function showsProvenance(): bool
    {
        return (bool) ($this->raw['provenance'] ?? true);
    }

    public function toArray(): array
    {
        return $this->raw;
    }
}
