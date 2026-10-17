<?php

namespace LaRecipe\Domains\Markdown\Contracts;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Turns the links authors write (relative .md files, so docs also read well on
 * GitHub) into the URLs readers follow. Implemented by the Delivery domain.
 */
interface LinkResolver
{
    /** A relative link to another page ("../billing.md#retries"), or null to leave it alone. */
    public function page(PageRef $from, string $target): ?string;

    /** A relative link to an image or file next to the page, or null to leave it alone. */
    public function asset(PageRef $from, string $target): ?string;
}
