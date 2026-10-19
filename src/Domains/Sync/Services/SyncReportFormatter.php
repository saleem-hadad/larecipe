<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\PageChange;
use LaRecipe\Domains\Sync\DTOs\SyncReport;
use LaRecipe\Domains\Sync\Enums\SyncTarget;

/**
 * Turns a sync report into the short text people read: one line per page
 * (what changed and why, with the files that triggered it), what was not
 * done and why, and the next step. The command prints it; a queued run logs it.
 */
final class SyncReportFormatter
{
    /** @return list<string> */
    public function changes(SyncReport $report): array
    {
        $lines = [];

        foreach ($report->applied() as $change) {
            $planned = $report->plan->page($change->subject);
            $label = $planned?->page->file ?? $change->subject;
            $label .= $change->movedTo !== null ? " -> {$change->movedTo}" : '';
            $lines[] = "{$label} ({$this->outcome($change)}): ".($change->summary ?? $this->verb($change));

            if ($planned !== null && $planned->sourceFiles !== []) {
                $lines[] = '    triggered by '.implode(', ', $planned->sourceFiles);
            }

            if ($change->docsMigration !== null) {
                $lines[] = "    recorded in {$change->docsMigration}";
            }
        }

        return $lines;
    }

    /** @return list<string> writes that did not happen, and why */
    public function problems(SyncReport $report): array
    {
        $lines = [];

        foreach ($report->bySubject() as $change) {
            if (! $change->ok() && $change->outcome !== 'unchanged') {
                $lines[] = "{$change->subject} ({$change->outcome}): ".($change->message ?? 'not written');
            }
        }

        if ($report->pageBudgetReached) {
            $lines[] = 'The page budget ('.$report->plan->request->maxPages.') stopped the agent from changing more pages. Run again with --page, or raise --max-pages.';
        }

        if ($report->stepBudgetReached) {
            $lines[] = 'The agent used its whole step budget ('.$report->plan->request->maxSteps.' steps) and may not have finished. Check its work, then run again with --page for what is left, or raise --max-steps.';
        }

        foreach ($report->plan->skipped as $skipped) {
            $lines[] = ($skipped->page->file ?? $skipped->path()).' (skipped): over the page budget; '.$skipped->reason;
        }

        return $lines;
    }

    public function nextStep(SyncReport $report): string
    {
        $applied = $report->applied();
        $plan = $report->plan;

        if ($plan->request->dryRun) {
            return $applied === []
                ? 'Dry run: the agent planned no changes.'
                : 'Dry run: nothing was written. Run again without --dry-run to make these changes.';
        }

        if ($applied === []) {
            return 'No docs changed.';
        }

        if ($report->commit !== null) {
            return 'Committed as '.substr($report->commit, 0, 7).'. Review it with git show, then push it with your code.';
        }

        return match ($plan->target) {
            SyncTarget::WorkingTree => 'Review the diff (git diff -- '.rtrim((string) $plan->set->docsPath, '/').'/), then commit it with your code.',
            SyncTarget::DocsMigration => 'Review the docs migration ('.implode(', ', $report->docsMigrations() ?: $report->files).'), then commit it with your code.',
            SyncTarget::Database, SyncTarget::Remote => $report->drafted()
                ? 'Publish the drafts in the admin UI once you have reviewed them.'
                : 'Published as revisions (review: auto). Revert any of them from the admin UI.',
        };
    }

    /** @return list<string> everything, for a log */
    public function lines(SyncReport $report): array
    {
        return [
            'larecipe:sync on ['.$report->plan->set->key.'] with '.$report->agent.': '.count($report->applied()).' changed, '.$report->steps.' steps.',
            ...$this->changes($report),
            ...$this->problems($report),
            ...($report->error !== null ? ['Error: '.$report->error] : []),
            $this->nextStep($report),
        ];
    }

    private function outcome(PageChange $change): string
    {
        return $change->outcome === PageChange::PLANNED ? 'planned' : $change->outcome;
    }

    private function verb(PageChange $change): string
    {
        return match ($change->tool) {
            'move_page' => 'moved',
            'delete_page' => 'deleted',
            'update_navigation' => 'navigation updated',
            'update_manifest' => 'settings updated',
            default => 'updated',
        };
    }
}
