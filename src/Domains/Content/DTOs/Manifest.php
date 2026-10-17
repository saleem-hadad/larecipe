<?php

namespace LaRecipe\Domains\Content\DTOs;

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

    /** @return array{url: ?string, branch: string, docsPath: string} */
    public function repository(): array
    {
        $repository = (array) ($this->raw['repository'] ?? []);

        return [
            'url' => isset($repository['url']) ? rtrim((string) $repository['url'], '/') : null,
            'branch' => (string) ($repository['branch'] ?? 'main'),
            'docsPath' => trim((string) ($repository['docsPath'] ?? 'docs'), '/'),
        ];
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

    public function showsProvenance(): bool
    {
        return (bool) ($this->raw['provenance'] ?? true);
    }

    public function toArray(): array
    {
        return $this->raw;
    }
}

