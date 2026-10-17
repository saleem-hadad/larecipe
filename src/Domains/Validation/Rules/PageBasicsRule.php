<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * page.empty: a page with no content.
 * frontmatter.description.missing: a page without a description, which makes
 * weaker llms.txt entries, search snippets and list_pages results.
 */
final class PageBasicsRule implements PageRule
{
    public function check(PageSnapshot $page, PageContext $context): array
    {
        $issues = [];
        $id = $context->pageId($page->page->ref->path);

        if (trim($page->page->body) === '' && $page->page->frontmatter->get('type') !== 'openapi') {
            $issues[] = ValidationIssue::error('page.empty', 'The page has no content.', $id, file: $page->file, fix: 'Write the page body, or delete the page.');
        }

        if ($page->page->description === null || trim($page->page->description) === '') {
            $issues[] = ValidationIssue::warning('frontmatter.description.missing', 'Pages without a description get weaker llms.txt entries and search snippets.', $id, file: $page->file, fix: 'Add a one-sentence description to the frontmatter.');
        }

        return $issues;
    }
}
