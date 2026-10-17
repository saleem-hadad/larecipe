<?php

namespace LaRecipe\Domains\Validation\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Validates one page before it is written or published. Every writer (MCP
 * tools, the admin UI, docs migrations, larecipe:sync) goes through it.
 *
 * Foundation version: checks the essentials. The Validation domain's rules
 * (links, components, frontmatter schema, references) plug in here.
 */
final class ValidatePageAction
{
    public function execute(Page $page): ValidationReport
    {
        $issues = [];

        if (trim($page->body) === '' && $page->frontmatter->get('type') !== 'openapi') {
            $issues[] = new ValidationIssue(ValidationIssue::ERROR, 'page.empty', 'The page has no content.', $page->ref->path);
        }

        if ($page->description === null) {
            $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'frontmatter.description.missing', 'Pages without a description get weaker llms.txt entries and search snippets.', $page->ref->path, fix: 'Add a one-sentence description to the frontmatter.');
        }

        return new ValidationReport($issues, 1);
    }
}
