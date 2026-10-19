<?php

namespace LaRecipe\Domains\Search\Support;

use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Reads the version and locale in front of `_search` in a URL, the same way
 * page URLs read them: `/{prefix}[/{locale}][/{version}]/_search`.
 */
final class SearchScope
{
    /** @return array{0: string, 1: string}|null [version, locale], or null when the scope names neither */
    public static function resolve(DocSet $set, string $scope): ?array
    {
        $segments = array_values(array_filter(explode('/', trim($scope, '/')), fn (string $segment) => $segment !== ''));
        $locale = $set->defaultLocale()->code;
        $version = $set->defaultVersion()->id;

        if ($segments !== [] && $set->isMultilingual() && $segments[0] !== $locale && $set->locale($segments[0]) !== null) {
            $locale = array_shift($segments);
        }

        if ($segments !== [] && $set->isVersioned() && $set->version($segments[0]) !== null) {
            $version = array_shift($segments);
        }

        return $segments === [] ? [$version, $locale] : null;
    }
}
