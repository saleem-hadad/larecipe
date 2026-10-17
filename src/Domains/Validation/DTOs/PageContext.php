<?php

namespace LaRecipe\Domains\Validation\DTOs;

use LaRecipe\Domains\Validation\Contracts\LinkTargets;

/**
 * What page rules need to know beyond the page itself: where it sits, how the
 * set is set up, and which pages its links may point at.
 *
 * Before a write (strictLinks false), link problems are warnings: an agent
 * writing several pages links to the next one before it exists.
 */
final readonly class PageContext
{
    public function __construct(
        public string $version,
        public string $locale,
        public string $label,
        public ValidationSettings $settings,
        public LinkTargets $targets,
        public bool $privateVersion = false,
        public bool $strictLinks = true,
    ) {}

    public function pageId(string $path): string
    {
        return $this->label === '' ? $path : $this->label.'/'.$path;
    }
}
