<?php

namespace LaRecipe\Domains\Validation\Contracts;

use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * A check that looks at one page. Page rules run both before a write
 * (ValidatePageAction) and over a whole set (larecipe:validate).
 */
interface PageRule
{
    /** @return list<ValidationIssue> */
    public function check(PageSnapshot $page, PageContext $context): array;
}
