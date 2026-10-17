<?php

namespace LaRecipe\Domains\Validation\Contracts;

use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * A check that needs the whole set: the manifest, navigation, redirects,
 * duplicates, budgets and skills.
 */
interface SetRule
{
    /** @return list<ValidationIssue> */
    public function check(DocsSnapshot $docs): array;
}
