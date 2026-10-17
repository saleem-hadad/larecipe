<?php

namespace LaRecipe\Domains\Content\DTOs;

/**
 * An independent documentation site inside one app (/docs, /api, /handbook):
 * its infrastructure config plus its manifest.
 */
final readonly class DocSet
{
    public function __construct(
        public string $key,
        public SetConfig $config,
        public Manifest $manifest,
    ) {}

    /** @return list<Version> declared versions, or the single implicit version */
    public function versions(): array
    {
        return $this->manifest->isVersioned() ? $this->manifest->versions : [Version::implicit()];
    }

    public function defaultVersion(): Version
    {
        foreach ($this->versions() as $version) {
            if ($version->default) {
                return $version;
            }
        }

        return $this->versions()[0];
    }

    public function version(string $id): ?Version
    {
        foreach ($this->versions() as $version) {
            if ($version->id === $id) {
                return $version;
            }
        }

        return null;
    }

    /** @return list<Locale> declared locales, or the single fallback locale */
    public function locales(): array
    {
        return $this->manifest->locales !== [] ? $this->manifest->locales : [Locale::fallback()];
    }

    public function defaultLocale(): Locale
    {
        foreach ($this->locales() as $locale) {
            if ($locale->default) {
                return $locale;
            }
        }

        return $this->locales()[0];
    }

    public function locale(string $code): ?Locale
    {
        foreach ($this->locales() as $locale) {
            if ($locale->code === $code) {
                return $locale;
            }
        }

        return null;
    }

    public function isVersioned(): bool
    {
        return $this->manifest->isVersioned();
    }

    public function isMultilingual(): bool
    {
        return $this->manifest->isMultilingual();
    }

    public function landingPath(): string
    {
        return PageRef::normalizePath($this->manifest->landing ?? 'index');
    }

    public function ref(string $path, ?string $version = null, ?string $locale = null): PageRef
    {
        return new PageRef(
            $this->key,
            $version ?? $this->defaultVersion()->id,
            $locale ?? $this->defaultLocale()->code,
            PageRef::normalizePath($path),
        );
    }
}
