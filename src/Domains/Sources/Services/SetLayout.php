<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\Locale;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\Version;

/**
 * The versions and locales a set's content is spread over, read from its
 * manifest the same way DocSet does: a set without declared versions has one
 * implicit version, a set without declared locales has one fallback locale.
 */
final class SetLayout
{
    /** @return list<string> */
    public static function versions(?Manifest $manifest): array
    {
        if ($manifest === null || ! $manifest->isVersioned()) {
            return [Version::IMPLICIT];
        }

        return array_values(array_map(fn (Version $version) => $version->id, $manifest->versions));
    }

    /**
     * Versions whose content folders exist, plus the implicit version for a
     * versioned set's shared, set-level assets (theme.css, _assets/).
     *
     * @return list<string>
     */
    public static function assetVersions(?Manifest $manifest): array
    {
        $versions = self::versions($manifest);

        return $manifest !== null && $manifest->isVersioned() ? [...$versions, Version::IMPLICIT] : $versions;
    }

    public static function defaultVersion(?Manifest $manifest): string
    {
        if ($manifest === null || ! $manifest->isVersioned()) {
            return Version::IMPLICIT;
        }

        foreach ($manifest->versions as $version) {
            if ($version->default) {
                return $version->id;
            }
        }

        return $manifest->versions[0]->id;
    }

    /** @return list<string> */
    public static function locales(?Manifest $manifest): array
    {
        if ($manifest === null || $manifest->locales === []) {
            return [Locale::fallback()->code];
        }

        return array_values(array_map(fn (Locale $locale) => $locale->code, $manifest->locales));
    }

    public static function defaultLocale(?Manifest $manifest): string
    {
        foreach ($manifest?->locales ?? [] as $locale) {
            if ($locale->default) {
                return $locale->code;
            }
        }

        return $manifest?->locales[0]->code ?? Locale::fallback()->code;
    }

    public static function hasVersion(?Manifest $manifest, string $version): bool
    {
        return in_array($version, self::versions($manifest), true);
    }

    public static function hasLocale(?Manifest $manifest, string $locale): bool
    {
        return in_array($locale, self::locales($manifest), true);
    }
}
