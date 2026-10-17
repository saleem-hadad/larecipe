<?php

namespace LaRecipe\Domains\Agents\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Agents\DTOs\DoctorCheck;
use LaRecipe\Domains\Agents\DTOs\DoctorReport;
use LaRecipe\Domains\Agents\Laravel\Doctor\DocsDoctor;
use LaRecipe\Domains\Content\Services\SetRegistry;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * php artisan larecipe:doctor: how ready each doc set is for agents, graded
 * like AFDocs and checked from inside the app, with a fix for every finding.
 * Exits non-zero when a check fails.
 */
#[AsCommand(name: 'larecipe:doctor', description: 'Grade how ready your docs are for agents, with fixes')]
final class DoctorCommand extends Command
{
    protected $signature = 'larecipe:doctor
        {--set= : Only this doc set}
        {--json : Print the report as JSON}';

    protected $description = 'Grade how ready your docs are for agents, with fixes';

    public function handle(SetRegistry $sets, DocsDoctor $doctor): int
    {
        $only = $this->option('set');

        if (is_string($only) && ! $sets->has($only)) {
            $this->components->error("There is no doc set named \"{$only}\". Sets: ".implode(', ', array_keys($sets->configs())).'.');

            return self::INVALID;
        }

        $reports = array_map(
            fn (string $key) => $doctor->examine($sets->get($key)),
            is_string($only) ? [$only] : array_keys($sets->configs()),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode(array_map(fn (DoctorReport $report) => $report->toArray(), $reports), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            array_map(fn (DoctorReport $report) => $this->print($report), $reports);
        }

        return array_filter($reports, fn (DoctorReport $report) => $report->failed()) === [] ? self::SUCCESS : self::FAILURE;
    }

    private function print(DoctorReport $report): void
    {
        $this->newLine();
        $this->line("  <options=bold>{$report->set}</> <fg=gray>{$report->url}</>");
        $this->newLine();

        foreach ($report->checks as $check) {
            $status = match ($check->status) {
                DoctorCheck::PASS => '<fg=green;options=bold>PASS</>',
                DoctorCheck::WARN => '<fg=yellow;options=bold>WARN</>',
                DoctorCheck::FAIL => '<fg=red;options=bold>FAIL</>',
                default => '<fg=gray>SKIP</>',
            };

            $this->line("  {$status}  {$check->title}: {$check->message}");

            if ($check->fix !== null) {
                $this->line("        <fg=green>Fix:</> {$check->fix}");
            }
        }

        $this->newLine();
        $this->line("  Agent readiness: <options=bold>{$report->grade()}</> ({$report->score()} / 100)");
    }
}
