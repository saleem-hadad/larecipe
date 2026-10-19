<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\MarkdownScanner;

/**
 * snippets.missing: a snippet include (<<< @/app/Billing/Invoice.php#refund)
 * whose file or region is gone. Samples that come from real code are the
 * Real-Code Rule; this check is what turns a stale sample into a CI failure
 * instead of a reader's problem.
 *
 * Includes resolve only inside the set's snippet root, never outside it.
 */
final class SnippetsRule implements PageRule
{
    public function __construct(private readonly MarkdownScanner $scanner) {}

    public function check(PageSnapshot $page, PageContext $context): array
    {
        // A renderer that resolves includes replaces them in the HTML and reports
        // missing ones itself (through ComponentsRule); only check them here when
        // the include lines are still there as text.
        if ($page->rendered !== null && ! str_contains($page->rendered->html, '&lt;&lt;&lt; @/')) {
            return [];
        }

        $issues = [];
        $id = $context->pageId($page->page->ref->path);
        $root = $context->settings->snippetRoot;

        foreach ($this->scanner->snippets($page->page->body) as $snippet) {
            $line = $page->fileLine($snippet['line']);

            if ($root === null) {
                $issues[] = ValidationIssue::error('snippets.missing', "Snippet includes are off for this set, so @/{$snippet['path']} cannot be included.", $id, $line, 'Paste the code, or configure a snippet root for the set.', $page->file);

                continue;
            }

            $file = $this->resolve($root, $snippet['path']);

            if ($file === null) {
                $issues[] = ValidationIssue::error('snippets.missing', "The included file @/{$snippet['path']} does not exist.", $id, $line, 'Point the include at the file\'s current path.', $page->file);

                continue;
            }

            if ($snippet['region'] !== null && ! preg_match('/#region\s+'.preg_quote($snippet['region'], '/').'\b/', (string) file_get_contents($file))) {
                $issues[] = ValidationIssue::error('snippets.missing', "The region \"{$snippet['region']}\" is not in @/{$snippet['path']}.", $id, $line, "Mark the code with // #region {$snippet['region']} and // #endregion, or include another region.", $page->file);
            }
        }

        return $issues;
    }

    private function resolve(string $root, string $path): ?string
    {
        $base = realpath($root);
        $file = realpath($root.'/'.$path);

        return $base !== false && $file !== false && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file) ? $file : null;
    }
}
