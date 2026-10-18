<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * access.unknown-ability: a <Can> or <Cannot> block asks about an ability the
 * host never defined. It renders, but denies every reader, so the block is
 * silently lost (a typo hides content instead of leaking it).
 * access.redundant-gate: a gate block on a page that is itself private.
 *
 * The Access domain decides what is wrong (CheckGateBlocksAction); this rule
 * puts each finding on the line of the block that asks.
 */
final class GateBlocksRule implements PageRule
{
    public function check(PageSnapshot $page, PageContext $context): array
    {
        $issues = [];

        foreach ($page->gateIssues as $gate) {
            $issues[] = new ValidationIssue(
                $gate->level,
                $gate->rule,
                $gate->message,
                $context->pageId($page->page->ref->path),
                $page->lineOf('ability="'.$gate->ability.'"'),
                $gate->fix,
                null,
                $page->file,
            );
        }

        return $issues;
    }
}
