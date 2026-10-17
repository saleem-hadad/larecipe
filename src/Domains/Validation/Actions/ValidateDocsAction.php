<?php

namespace LaRecipe\Domains\Validation\Actions;

use LaRecipe\Domains\Validation\Contracts\DocsInspector;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;
use LaRecipe\Domains\Validation\Services\DocsValidator;

/**
 * Validates a whole doc set, or one version of it: every rule over every page
 * plus the set-level checks. What larecipe:validate prints and validate_docs returns.
 */
final class ValidateDocsAction
{
    public function __construct(
        private readonly DocsInspector $inspector,
        private readonly DocsValidator $validator,
    ) {}

    public function execute(string $set, ?string $version = null): ValidationReport
    {
        return $this->validator->set($this->inspector->snapshot($set, $version));
    }
}
