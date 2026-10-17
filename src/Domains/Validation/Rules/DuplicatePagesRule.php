<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * pages.duplicate: two files that become the same URL ("guides.md" and
 * "guides/index.md", or "Install.md" and "install.md"). Only one of them can
 * ever be served, and which one depends on the file system.
 */
final class DuplicatePagesRule implements SetRule
{
    public function check(DocsSnapshot $docs): array
    {
        $issues = [];

        foreach ($docs->versions as $version) {
            foreach ($version->duplicates as $duplicate) {
                $issues[] = ValidationIssue::error(
                    'pages.duplicate',
                    'These files all become the page "'.$duplicate['path'].'": '.implode(', ', $duplicate['files']).'.',
                    $version->pageId($duplicate['path']),
                    null,
                    'Keep one file per page: rename or delete the others.',
                    $duplicate['files'][0] ?? null,
                );
            }
        }

        return $issues;
    }
}
