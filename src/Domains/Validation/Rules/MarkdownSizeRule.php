<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * markdown.size: a page whose agent Markdown is larger than the budget
 * (50,000 characters by default, the size agents start truncating at).
 */
final class MarkdownSizeRule implements PageRule
{
    public function check(PageSnapshot $page, PageContext $context): array
    {
        $budget = $context->settings->markdownBudget;

        if ($page->markdownSize === null || $budget <= 0 || $page->markdownSize <= $budget) {
            return [];
        }

        return [ValidationIssue::warning(
            'markdown.size',
            sprintf('The page\'s agent Markdown is %s characters, over the %s budget; agents may cut it off.', number_format($page->markdownSize), number_format($budget)),
            $context->pageId($page->page->ref->path),
            null,
            'Split the page into smaller pages, one task or topic each.',
            $page->file,
        )];
    }
}
