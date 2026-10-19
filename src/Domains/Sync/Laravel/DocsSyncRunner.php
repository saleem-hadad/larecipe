<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Closure;
use Illuminate\Contracts\Container\Container;
use Laravel\Ai\Responses\AgentResponse;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Mcp\AuthorSession;
use LaRecipe\Domains\Sync\Actions\CommitDocsAction;
use LaRecipe\Domains\Sync\DTOs\SyncPlan;
use LaRecipe\Domains\Sync\DTOs\SyncReport;
use LaRecipe\Domains\Sync\Enums\SyncTarget;
use LaRecipe\Domains\Sync\Exceptions\SyncRefused;
use LaRecipe\Domains\Sync\Services\DocsFiles;
use LaRecipe\Domains\Sync\Services\SyncInstructions;
use LaRecipe\Domains\Sync\Services\SyncLedger;
use LaRecipe\Domains\Sync\Services\SyncPrompt;
use Throwable;

/**
 * Runs the docs agent for one planned sync and reports what it did.
 *
 * In order: check --commit can commit only the run's own files, build the
 * guarded tools, put the author tools under the run's channel and author,
 * prompt the agent, then work out which files changed and commit them when
 * asked. A provider or tool error stops the run but never hides what was
 * already written: the report carries both.
 *
 * The channel follows where the change lands (SyncTarget): a database set
 * inside a repository writes as a pull request change (published locally,
 * recorded in the branch's docs migration); everywhere else the change is
 * the sync agent's (Channel::Sync), which the review mode governs.
 */
final class DocsSyncRunner
{
    public const AUTHOR = 'larecipe-sync';

    public function __construct(
        private readonly Container $app,
        private readonly SyncToolbox $toolbox,
        private readonly SyncInstructions $instructions,
        private readonly SyncPrompt $prompt,
        private readonly DocsFiles $files,
        private readonly CommitDocsAction $commit,
        private readonly SyncReadiness $readiness,
        private readonly int $timeout = 120,
    ) {}

    /** Why the planned run cannot start, or null. Checked by the command before it asks for consent. */
    public function preflight(SyncPlan $plan): ?string
    {
        if (! $plan->request->commit) {
            return null;
        }

        $dirty = $this->files->uncommitted($plan);

        return $dirty === [] ? null : '--commit commits only what the agent writes, and these docs files already have uncommitted changes: '
            .implode(', ', array_slice($dirty, 0, 8)).(count($dirty) > 8 ? ', ...' : '')
            .'. Commit or stash them first, or run without --commit.';
    }

    public function execute(SyncPlan $plan): SyncReport
    {
        $request = $plan->request;
        $report = new SyncReport($plan, $this->readiness->agentName($request));

        if (($problem = $this->preflight($plan)) !== null) {
            return $report->with(['error' => $problem]);
        }

        $ledger = new SyncLedger($request->maxPages, $request->dryRun);

        try {
            $tools = $this->toolbox->for($plan, $ledger);
        } catch (SyncRefused $refused) {
            return $report->with(['error' => $refused->getMessage()]);
        }

        $before = $this->files->snapshot($plan);
        $restore = $this->authorAs($plan);

        try {
            $response = (new DocsMaintainer(
                $this->instructions->build($plan),
                $tools,
                $request->maxSteps,
                $this->readiness->provider($request),
                $this->readiness->model($request),
                $this->timeout,
            ))->prompt($this->prompt->build($plan));

            $report = $report->with([
                'summary' => trim($response->text),
                'steps' => $response->steps->count(),
                'stepBudgetReached' => $this->usedEveryStep($response, $request->maxSteps),
            ]);
        } catch (Throwable $exception) {
            $report = $report->with(['error' => 'The run stopped: '.$this->describe($exception)]);
        } finally {
            $restore();
        }

        $report = $report->with([
            'changes' => $ledger->changes(),
            'pageBudgetReached' => $ledger->budgetReached(),
            'files' => $this->files->changed($before, $this->files->snapshot($plan)),
        ]);

        if (! $request->commit || $report->failed() || $report->files === []) {
            return $report;
        }

        $sha = $this->commit->execute($report);

        return $sha !== null
            ? $report->with(['commit' => $sha])
            : $report->with(['error' => 'git refused the commit. The docs change is still in the working tree: review it and commit it yourself.']);
    }

    /**
     * Point the author tools at this run: local, with the run's channel and
     * the agent as author. Returns the undo, so a queue worker's next job
     * gets the ordinary binding back.
     *
     * @return Closure(): void
     */
    private function authorAs(SyncPlan $plan): Closure
    {
        if ($plan->target === SyncTarget::Remote) {
            return fn () => null;
        }

        $this->app->instance(AuthorSession::class, new AuthorSession(
            $this->app,
            $this->app->make(SourceResolver::class),
            $this->app->make(Authorizer::class),
            true,
            $plan->target === SyncTarget::DocsMigration ? Channel::PullRequest : Channel::Sync,
            Provenance::agent(self::AUTHOR),
        ));

        return function () {
            $this->app->forgetInstance(AuthorSession::class);
        };
    }

    /** The last step still asked for tools: the step budget, not the agent, ended the run. */
    private function usedEveryStep(AgentResponse $response, int $maxSteps): bool
    {
        $last = $response->steps->last();

        return $response->steps->count() >= $maxSteps && $last !== null && $last->toolCalls !== [];
    }

    private function describe(Throwable $exception): string
    {
        $message = trim($exception->getMessage()) ?: class_basename($exception);

        return mb_strlen($message) > 400 ? mb_substr($message, 0, 400).'...' : $message;
    }
}
