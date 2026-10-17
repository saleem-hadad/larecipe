<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;

/**
 * Page addresses as people write them in docs migrations and commands:
 * "3.x/billing/exports" for a versioned set, "3.x/ar/billing/exports" for a
 * page in another locale, "billing/exports" for an unversioned set.
 *
 * The version comes first when the set is versioned; a locale segment follows
 * only for a non-default locale, mirroring the folder layout of a repository set.
 */
final class PageAddresses
{
    public function parse(string $address, string $set, ?Manifest $manifest): PageRef
    {
        $segments = array_values(array_filter(explode('/', trim(str_replace('\\', '/', $address), '/')), fn ($s) => $s !== ''));
        $version = SetLayout::defaultVersion($manifest);

        if ($manifest !== null && $manifest->isVersioned()) {
            if ($segments === [] || ! SetLayout::hasVersion($manifest, $segments[0])) {
                throw InvalidContentChange::invalidAddress($address, 'versioned sets start the address with a version ('.implode(', ', SetLayout::versions($manifest)).').');
            }

            $version = array_shift($segments);
        }

        $locale = SetLayout::defaultLocale($manifest);

        if ($segments !== [] && $segments[0] !== $locale && SetLayout::hasLocale($manifest, $segments[0])) {
            $locale = array_shift($segments);
        }

        return new PageRef($set, $version, $locale, PageRef::normalizePath(implode('/', $segments)));
    }

    public function format(PageRef $ref, ?Manifest $manifest): string
    {
        $segments = [];

        if ($manifest !== null && $manifest->isVersioned()) {
            $segments[] = $ref->version;
        }

        if ($ref->locale !== SetLayout::defaultLocale($manifest)) {
            $segments[] = $ref->locale;
        }

        $segments[] = $ref->path;

        return implode('/', $segments);
    }
}
