<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * Loads one page from its set's source, or null when it does not exist.
 */
final class GetPageAction
{
    public function __construct(private readonly SourceResolver $sources) {}

    public function execute(PageRef $ref): ?Page
    {
        return $this->sources->for($ref->set)->page($ref);
    }
}
