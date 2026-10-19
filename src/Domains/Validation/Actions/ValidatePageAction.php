<?php

namespace LaRecipe\Domains\Validation\Actions;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Validation\Contracts\DocsInspector;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;
use LaRecipe\Domains\Validation\Services\DocsValidator;

/**
 * Validates one page before it is written or published. Every writer (MCP
 * tools, the admin UI, docs migrations, larecipe:sync) goes through it, so a
 * page with an unknown component, broken frontmatter or a missing snippet never
 * lands.
 *
 * It runs the same page rules as larecipe:validate, with one difference: link
 * problems are warnings here, because an agent writing several pages links to
 * the next one before it exists. The full run catches whatever is still broken.
 */
final class ValidatePageAction
{
    public function __construct(
        private readonly DocsInspector $inspector,
        private readonly DocsValidator $validator,
    ) {}

    public function execute(Page $page): ValidationReport
    {
        $issues = $this->validator->page($this->inspector->inspect($page), $this->inspector->context($page));

        if ($this->inspector->hasSet($page->ref->set)) {
            $issues = array_map(fn (ValidationIssue $issue) => $issue->inSet($page->ref->set), $issues);
        }

        return new ValidationReport($issues, 1);
    }
}
