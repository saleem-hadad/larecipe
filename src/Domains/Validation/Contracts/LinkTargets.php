<?php

namespace LaRecipe\Domains\Validation\Contracts;

/**
 * The pages a link may point at, in one version and locale of a set.
 */
interface LinkTargets
{
    /** @return list<string>|null the ids on the page, or null when there is no page at the path */
    public function anchors(string $version, string $locale, string $path): ?array;

    public function isPrivate(string $version, string $locale, string $path): bool;

    /** @return list<string> every page path, for "did you mean" suggestions */
    public function paths(string $version, string $locale): array;
}
