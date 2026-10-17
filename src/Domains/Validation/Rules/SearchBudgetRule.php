<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * search.budget: a version whose client search index would be larger than the
 * budget (300 KB gzip by default, about 2,000 sections). Past it, readers wait
 * on the first search; the server driver avoids shipping an index at all.
 *
 * The size is an estimate from the rendered text of every page (titles,
 * descriptions, body), compressed the way the index is served.
 */
final class SearchBudgetRule implements SetRule
{
    public function check(DocsSnapshot $docs): array
    {
        $budget = $docs->settings->searchBudget;

        if ($docs->settings->searchDriver !== 'client' || $budget <= 0) {
            return [];
        }

        $issues = [];

        foreach ($docs->versions as $version) {
            $records = [];

            foreach ($version->pages as $path => $page) {
                $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $page->rendered?->html)));
                $records[] = [$path, $page->page->title, $page->page->description, $text];
            }

            $size = $records === [] ? 0 : strlen((string) gzencode((string) json_encode($records, JSON_UNESCAPED_UNICODE), 6));

            if ($size > $budget) {
                $issues[] = ValidationIssue::warning(
                    'search.budget',
                    sprintf('The search index for %s would be about %s KB gzip, over the %s KB budget.', $version->label === '' ? 'this set' : $version->label, number_format($size / 1024), number_format($budget / 1024)),
                    $version->label === '' ? null : $version->label,
                    null,
                    "Set search.driver to 'server' for this set, or split the docs into more versions or sets.",
                );
            }
        }

        return $issues;
    }
}
