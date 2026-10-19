<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\PlannedPage;
use LaRecipe\Domains\Sync\DTOs\SyncPlan;
use LaRecipe\Domains\Sync\Enums\SyncMode;

/**
 * The task the docs agent gets for one run: what to do, on which pages, from
 * which change, where its writes land, and how to report back.
 *
 * Built from the plan, so the agent works on exactly the pages the person
 * saw in the confirmation. Page titles and paths come from the docs, so they
 * are quoted as data.
 */
final class SyncPrompt
{
    /** An audit lists at most this many pages; the agent finds the rest with list_pages. */
    private const AUDIT_LIST_LIMIT = 150;

    public function build(SyncPlan $plan): string
    {
        $set = $plan->set;
        $scope = "set `{$set->key}`".($set->versioned ? ", version `{$plan->version}`" : '').", locale `{$plan->locale}`";

        $lines = [match ($plan->mode) {
            SyncMode::Diff => "Update the docs pages this code change left stale ({$scope}).",
            SyncMode::Pages => "Update these docs pages so they match the current code ({$scope}).",
            SyncMode::Audit => "Audit the docs ({$scope}) with the audit workflow, then fix the most important problems you find, P1 first.",
        }];

        $lines[] = '';

        if ($plan->base !== null || $plan->changedFiles !== []) {
            $lines[] = "The change: {$plan->range()}. Read it with git_diff, which covers this range by default.";

            if ($plan->changedFiles !== []) {
                $lines[] = 'Changed files: '.implode(', ', array_slice($plan->changedFiles, 0, 60)).(count($plan->changedFiles) > 60 ? ', and '.(count($plan->changedFiles) - 60).' more' : '').'.';
            }

            $lines[] = '';
        }

        $lines[] = $plan->mode === SyncMode::Audit ? 'The pages, most urgent first:' : 'The pages:';

        foreach (array_slice($plan->pages, 0, $plan->mode === SyncMode::Audit ? self::AUDIT_LIST_LIMIT : count($plan->pages)) as $index => $page) {
            $lines[] = ($index + 1).'. '.$this->page($page);
        }

        if ($plan->mode === SyncMode::Audit && count($plan->pages) > self::AUDIT_LIST_LIMIT) {
            $lines[] = 'And '.(count($plan->pages) - self::AUDIT_LIST_LIMIT).' more pages: see list_pages.';
        }

        $lines[] = '';
        $lines[] = 'Where your writes go: '.$plan->target->describe().'.';
        $lines[] = "Budget: at most {$plan->request->maxPages} changed pages and {$plan->request->maxSteps} steps.";

        if ($plan->request->dryRun) {
            $lines[] = 'This is a dry run: nothing you write is saved. Plan every change as if it were.';
        }

        $lines[] = '';
        $lines[] = $plan->mode === SyncMode::Audit
            ? 'Finish with the audit report the workflow describes (counts first, then P1, P2 and P3 items, one line each), then one line per page you changed: `path: what changed and why`.'
            : 'Finish with one line per page you changed, `path: what changed and why`, then one line per listed page you left alone and why.';

        return implode("\n", $lines);
    }

    private function page(PlannedPage $page): string
    {
        $where = $page->page->file !== null ? " ({$page->page->file})" : '';
        $sources = $page->page->sources === [] ? 'none' : implode(', ', $page->page->sources);

        return "`{$page->path()}`{$where}, titled ".json_encode($page->page->title, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ."; sources: {$sources}; why: {$page->reason}.";
    }
}
