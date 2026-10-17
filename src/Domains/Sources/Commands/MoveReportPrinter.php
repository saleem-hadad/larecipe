<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\View\Components\Factory;
use LaRecipe\Domains\Sources\DTOs\MoveReport;

/**
 * Prints what a source move, export or import did, the same way for all three.
 */
final class MoveReportPrinter
{
    public function print(Factory $components, MoveReport $report): void
    {
        $plan = $report->plan;

        $components->twoColumnDetail('Manifest (larecipe.json)', $plan->manifest ? 'yes' : 'none');
        $components->twoColumnDetail('Pages', (string) count($plan->pages));
        $components->twoColumnDetail('Navigation files', (string) $plan->navigations);
        $components->twoColumnDetail('Assets', (string) $plan->assets);

        foreach ($plan->warnings as $warning) {
            $components->warn($warning);
        }

        foreach ($report->validation->errors() as $issue) {
            $components->error("{$issue->page}: {$issue->message} ({$issue->rule})");
        }

        if ($report->verified === true) {
            $report->mismatches === []
                ? $components->info('Verified: every page, navigation, manifest and asset has the same content hash in the target.')
                : $components->bulletList($report->mismatches);
        }
    }
}
