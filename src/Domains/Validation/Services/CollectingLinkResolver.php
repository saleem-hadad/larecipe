<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;

/**
 * Lets the renderer report every relative .md link (with its line) without
 * rewriting it, so validation checks the links exactly as the author wrote them.
 */
final class CollectingLinkResolver implements LinkResolver
{
    public function page(PageRef $from, string $target): ?string
    {
        return preg_match('/\.md(?:[?#].*)?$/i', $target) ? $target : null;
    }

    public function asset(PageRef $from, string $target): ?string
    {
        return null;
    }
}
