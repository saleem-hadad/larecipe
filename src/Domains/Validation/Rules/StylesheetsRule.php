<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * assets.missing: a stylesheet listed under assets.css in larecipe.json that
 * the set does not have. Pages would load without it, so the overrides it
 * should carry silently disappear.
 */
final class StylesheetsRule implements SetRule
{
    public function check(DocsSnapshot $docs): array
    {
        $issues = [];

        foreach ($docs->stylesheets as $file => $exists) {
            if (! $exists) {
                $issues[] = ValidationIssue::error(
                    'assets.missing',
                    "larecipe.json lists {$file} under assets.css, but there is no such file next to larecipe.json.",
                    file: $docs->manifestFile,
                    fix: "Add {$file} next to larecipe.json, or remove it from assets.css.",
                );
            }
        }

        return $issues;
    }
}
