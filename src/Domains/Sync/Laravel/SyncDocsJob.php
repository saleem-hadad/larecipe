<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LaRecipe\Domains\Sync\Actions\PlanSyncAction;
use LaRecipe\Domains\Sync\DTOs\SyncRequest;
use LaRecipe\Domains\Sync\Exceptions\SyncRefused;
use LaRecipe\Domains\Sync\Services\SyncReportFormatter;
use Psr\Log\LoggerInterface;

/**
 * `larecipe:sync --queue`: the same run as an ordinary queued job, so it shows
 * up in Horizon or Nightwatch and runs where the queue worker runs.
 *
 * It carries the request, not the plan, and plans again when it runs, against
 * the files and the database as they are then. The report goes to the log.
 * It is never scheduled or dispatched by LaRecipe itself: a person asked for
 * this run and confirmed it (no AI in CI, the Check-Never-Write CI Rule).
 */
final class SyncDocsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** One attempt: a retry would spend tokens again and might write twice. */
    public int $tries = 1;

    public int $timeout = 1800;

    /** @param array<string, mixed> $request SyncRequest::toArray() */
    public function __construct(public array $request) {}

    public function handle(SyncReadiness $readiness, PlanSyncAction $planner, DocsSyncRunner $runner, SyncReportFormatter $formatter, LoggerInterface $log): void
    {
        $request = SyncRequest::fromArray($this->request);

        if (($problem = $readiness->problem($request)) !== null) {
            $log->warning('larecipe:sync could not start: '.$problem);

            return;
        }

        try {
            $plan = $planner->execute($request);
        } catch (SyncRefused $refused) {
            $log->warning('larecipe:sync could not start: '.$refused->getMessage());

            return;
        }

        if (! $plan->hasWork()) {
            $log->info('larecipe:sync on ['.$plan->set->key.'] found nothing to update. '.implode(' ', $plan->notes));

            return;
        }

        $report = $runner->execute($plan);
        $message = implode("\n", $formatter->lines($report));

        $report->failed() ? $log->error($message) : $log->info($message);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['larecipe', 'larecipe:sync', 'set:'.($this->request['set'] ?? 'default')];
    }
}
