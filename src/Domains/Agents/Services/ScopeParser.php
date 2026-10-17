<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Reads the path in front of an agent file ("ar/2.x/billing" in
 * /handbook/ar/2.x/billing/llms.txt): an optional locale, then the version on
 * a versioned set, then whatever is left (a group slug path).
 *
 * The same order as page URLs, so agent files sit next to the pages they cover.
 */
final class ScopeParser
{
    /** @return array{locale: string, version: ?string, rest: list<string>} */
    public function parse(DocSet $set, ?string $scope): array
    {
        $segments = array_values(array_filter(explode('/', trim((string) $scope, '/')), fn (string $s) => $s !== ''));
        $locale = $set->defaultLocale()->code;

        if ($segments !== [] && $set->isMultilingual() && $segments[0] !== $locale && $set->locale($segments[0]) !== null) {
            $locale = array_shift($segments);
        }

        $version = $set->isVersioned() ? null : $set->defaultVersion()->id;

        if ($set->isVersioned() && $segments !== [] && $set->version($segments[0]) !== null) {
            $version = array_shift($segments);
        }

        return ['locale' => $locale, 'version' => $version, 'rest' => $segments];
    }
}
