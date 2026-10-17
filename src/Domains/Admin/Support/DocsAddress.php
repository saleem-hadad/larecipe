<?php

namespace LaRecipe\Domains\Admin\Support;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A page address as a docs migration writes it: "3.x/billing/exports" in a
 * versioned set, "3.x/ar/billing/exports" in another locale, and
 * "billing/exports" in an unversioned set (the version comes first, the
 * locale only when it is not the default).
 */
final class DocsAddress
{
    public static function of(DocSet $set, PageRef $ref): string
    {
        $segments = [];

        if ($set->isVersioned()) {
            $segments[] = $ref->version;
        }

        if ($ref->locale !== $set->defaultLocale()->code) {
            $segments[] = $ref->locale;
        }

        $segments[] = $ref->path;

        return implode('/', $segments);
    }
}
