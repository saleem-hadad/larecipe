<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Reads the page an agent means. Agents pass whatever they have: a path from
 * list_pages ("guides/webhooks"), a .md URL from llms.txt, a page URL from a
 * citation, with or without an #anchor. All of them resolve to the same page.
 *
 * The version and locale in a URL count only when the caller did not pass them.
 */
final class PathResolver
{
    /** @return array{ref: PageRef, anchor: ?string} */
    public function resolve(DocSet $set, string $input, ?string $version, ?string $locale, string $defaultVersion, string $defaultLocale): array
    {
        $input = trim($input);
        $anchor = null;

        if (str_contains($input, '#')) {
            [$input, $anchor] = explode('#', $input, 2);
            $anchor = trim($anchor) === '' ? null : trim($anchor);
        }

        $input = (string) preg_replace('/\?.*$/', '', $input);
        $absolute = str_starts_with($input, '/');

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = (string) parse_url($input, PHP_URL_PATH);
            $absolute = true;
        }

        $input = (string) preg_replace('/\.md$/i', '', rawurldecode($input));
        $segments = array_values(array_filter(explode('/', trim($input, '/')), fn (string $s) => $s !== ''));

        if ($absolute) {
            $segments = $this->withoutPrefix($set, $segments);
        }

        $urlLocale = null;

        if ($segments !== [] && $set->isMultilingual() && $set->locale($segments[0]) !== null) {
            $urlLocale = array_shift($segments);
        }

        $urlVersion = null;

        if ($segments !== [] && $set->isVersioned() && $set->version($segments[0]) !== null) {
            $urlVersion = array_shift($segments);
        }

        $ref = new PageRef(
            $set->key,
            $version ?? $urlVersion ?? $defaultVersion,
            $locale ?? $urlLocale ?? $defaultLocale,
            PageRef::normalizePath(implode('/', $segments)),
        );

        return ['ref' => $ref, 'anchor' => $anchor];
    }

    /**
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function withoutPrefix(DocSet $set, array $segments): array
    {
        $prefix = array_values(array_filter(explode('/', $set->config->prefix), fn (string $s) => $s !== ''));

        if ($prefix !== [] && array_slice($segments, 0, count($prefix)) === $prefix) {
            return array_slice($segments, count($prefix));
        }

        return $segments;
    }
}
