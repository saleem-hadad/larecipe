<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Runs the rules: page rules over one page, or page rules over every page of a
 * snapshot plus the set rules. Issues come out errors first, then by page.
 */
final class DocsValidator
{
    public function __construct(private readonly RuleRegistry $rules) {}

    /** @return list<ValidationIssue> */
    public function page(PageSnapshot $page, PageContext $context): array
    {
        $issues = [];

        foreach ($this->rules->pageRules() as $rule) {
            array_push($issues, ...$rule->check($page, $context));
        }

        return $issues;
    }

    public function set(DocsSnapshot $docs): ValidationReport
    {
        $targets = new SnapshotLinkTargets($docs);
        $labels = [];
        $issues = [];

        foreach ($docs->versions as $version) {
            $labels[$version->label] = [$version->version, $version->locale];
        }

        foreach ($docs->versions as $version) {
            $context = new PageContext($version->version, $version->locale, $version->label, $docs->settings, $targets, $version->private, labels: $labels);

            foreach ($version->pages as $page) {
                array_push($issues, ...$this->page($page, $context));
            }
        }

        foreach ($this->rules->setRules() as $rule) {
            array_push($issues, ...$rule->check($docs));
        }

        $issues = array_map(fn (ValidationIssue $issue) => $issue->inSet($docs->set), $issues);

        usort($issues, fn (ValidationIssue $a, ValidationIssue $b) => [$a->level !== ValidationIssue::ERROR, $a->page ?? '', $a->line ?? 0]
            <=> [$b->level !== ValidationIssue::ERROR, $b->page ?? '', $b->line ?? 0]);

        return new ValidationReport($issues, $docs->pageCount());
    }
}
