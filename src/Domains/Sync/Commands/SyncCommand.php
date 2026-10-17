<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\PlanSyncAction;
use LaRecipe\Domains\Sync\DTOs\PlannedPage;
use LaRecipe\Domains\Sync\DTOs\SyncPlan;
use LaRecipe\Domains\Sync\DTOs\SyncReport;
use LaRecipe\Domains\Sync\DTOs\SyncRequest;
use LaRecipe\Domains\Sync\Enums\SyncMode;
use LaRecipe\Domains\Sync\Exceptions\SyncRefused;
use LaRecipe\Domains\Sync\Laravel\DocsSyncRunner;
use LaRecipe\Domains\Sync\Laravel\SyncDocsJob;
use LaRecipe\Domains\Sync\Laravel\SyncReadiness;
use LaRecipe\Domains\Sync\Services\SyncReportFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

/**
 * `php artisan larecipe:sync`: the server-side docs agent, on demand, on the
 * owner's own laravel/ai provider key (ARCHITECTURE.md 13.6).
 *
 * For extra power only: a whole-set audit, a developer without a local coding
 * agent, or an edit to a production database set outside a pull request. The
 * owner's own coding session stays the primary author, and nothing in
 * LaRecipe runs this in CI (the Check-Never-Write CI Rule).
 *
 * It plans first, for free (the pages, the change, where writes land), shows
 * the plan with the provider and model, and asks before spending a token.
 */
#[AsCommand(name: 'larecipe:sync')]
final class SyncCommand extends Command
{
    protected $signature = 'larecipe:sync
        {--base= : Compare with this branch or commit (default: the default branch)}
        {--head=HEAD : The end of the range}
        {--page=* : Update this page instead (a path such as guides/webhooks, or its file); repeatable}
        {--audit : Review the whole set and fix what matters most}
        {--set= : The doc set (default: the default set)}
        {--docs-version= : The version to work on (default: the set\'s default version)}
        {--locale= : The locale to work on (default: the set\'s default locale)}
        {--dry-run : Report what the agent would change, and write nothing}
        {--max-pages= : The most pages this run may change (default: larecipe.ai.sync.max_pages)}
        {--max-steps= : The most agent steps this run may take (default: larecipe.ai.sync.max_steps)}
        {--provider= : The laravel/ai provider (default: larecipe.ai.sync.provider, then ai.default)}
        {--model= : The model (default: larecipe.ai.sync.model, then the provider\'s default)}
        {--remote= : The MCP endpoint of a production database set, to change it outside a pull request}
        {--commit : Commit the docs change on the current branch}
        {--queue : Run as a queued job (visible in Horizon or Nightwatch)}
        {--yes : Run without asking}';

    protected $description = 'Update the docs with an AI agent on your own provider key (on demand, never in CI)';

    public function handle(SyncReadiness $readiness, PlanSyncAction $planner, SyncReportFormatter $formatter): int
    {
        $request = $this->request();

        if (($problem = $readiness->problem($request)) !== null) {
            $this->components->error('The docs agent cannot run yet.');
            $this->printLines(explode("\n", $problem));

            return self::FAILURE;
        }

        try {
            $plan = $planner->execute($request);
        } catch (SyncRefused $refused) {
            $this->components->error($refused->getMessage());

            return self::INVALID;
        }

        $this->showPlan($plan, $readiness->agentName($request));

        if (! $plan->hasWork()) {
            $this->components->info($plan->mode === SyncMode::Audit ? 'There are no pages to audit.' : 'Nothing to update: no page documents what this change touched, or the change already updated it.');

            return self::SUCCESS;
        }

        $runner = $this->laravel->make(DocsSyncRunner::class);

        if (($problem = $runner->preflight($plan)) !== null) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        if (! $this->consent($readiness->provider($request))) {
            return $this->input->isInteractive() ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('queue')) {
            return $this->queue($request);
        }

        $this->components->info('The docs agent is working. This can take a few minutes.');

        return $this->report($runner->execute($plan), $formatter);
    }

    private function request(): SyncRequest
    {
        $config = $this->laravel['config'];

        return new SyncRequest(
            set: $this->stringOption('set'),
            base: $this->stringOption('base'),
            head: $this->stringOption('head') ?? 'HEAD',
            pages: array_values(array_filter(array_map('strval', (array) $this->option('page')), fn (string $page) => trim($page) !== '')),
            audit: (bool) $this->option('audit'),
            version: $this->stringOption('docs-version'),
            locale: $this->stringOption('locale'),
            dryRun: (bool) $this->option('dry-run'),
            maxPages: max(1, (int) ($this->stringOption('max-pages') ?? $config->get('larecipe.ai.sync.max_pages', 20))),
            maxSteps: max(1, (int) ($this->stringOption('max-steps') ?? $config->get('larecipe.ai.sync.max_steps', 30))),
            provider: $this->stringOption('provider'),
            model: $this->stringOption('model'),
            commit: (bool) $this->option('commit'),
            remote: $this->stringOption('remote'),
        );
    }

    private function showPlan(SyncPlan $plan, string $agent): void
    {
        $set = $plan->set;
        $request = $plan->request;

        $this->newLine();
        $this->line('  <options=bold>LaRecipe docs agent</>');
        $this->newLine();
        $this->components->twoColumnDetail('Doc set', "{$set->name} ({$set->key})".($set->versioned ? ", version {$plan->version}" : '').", {$plan->locale}");
        $this->components->twoColumnDetail('Task', match ($plan->mode) {
            SyncMode::Diff => 'update the pages this change left stale',
            SyncMode::Pages => 'update the pages you named',
            SyncMode::Audit => 'audit the set and fix what matters most',
        });

        if ($plan->mode !== SyncMode::Audit) {
            $this->components->twoColumnDetail('Change', $plan->range().', '.count($plan->changedFiles).' changed '.(count($plan->changedFiles) === 1 ? 'file' : 'files'));
        }

        $this->components->twoColumnDetail('Agent', $agent);
        $this->components->twoColumnDetail('Budget', "{$request->maxPages} ".($request->maxPages === 1 ? 'page' : 'pages').", {$request->maxSteps} steps");
        $this->components->twoColumnDetail('Writes', $request->dryRun ? 'nothing (dry run)' : $plan->target->describe());

        if ($request->commit) {
            $this->components->twoColumnDetail('Commit', 'on the current branch, with the agent as co-author');
        }

        if ($plan->pages !== []) {
            $this->newLine();
            $this->line('  '.($plan->mode === SyncMode::Audit ? count($plan->pages).' pages to review, most urgent first:' : 'Pages:'));

            foreach (array_slice($plan->pages, 0, $plan->mode === SyncMode::Audit ? 10 : count($plan->pages)) as $page) {
                $this->pageLine($page);
            }

            if ($plan->mode === SyncMode::Audit && count($plan->pages) > 10) {
                $this->line('  <fg=gray>and '.(count($plan->pages) - 10).' more</>');
            }
        }

        foreach ($plan->notes as $note) {
            $this->line("  <fg=gray>{$note}</>");
        }

        $this->newLine();
    }

    private function pageLine(PlannedPage $page): void
    {
        $this->components->twoColumnDetail($page->page->file ?? $page->path(), "<fg=gray>{$page->reason}</>");
    }

    private function consent(string $provider): bool
    {
        if ($this->option('yes')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('Not running the docs agent without your consent. Run again with --yes to run it.');

            return false;
        }

        return confirm("Run the docs agent now? It spends tokens on your {$provider} key.", default: false);
    }

    private function queue(SyncRequest $request): int
    {
        $queue = $this->laravel['config']->get('larecipe.ai.sync.queue');
        $job = new SyncDocsJob($request->toArray());

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        dispatch($job);

        $this->components->info('Queued the docs agent'.(is_string($queue) && $queue !== '' ? " on the [{$queue}] queue" : '').'. Follow it in Horizon or Nightwatch; its report goes to your log.');

        return self::SUCCESS;
    }

    private function report(SyncReport $report, SyncReportFormatter $formatter): int
    {
        $changes = $formatter->changes($report);

        if ($changes !== []) {
            $this->line('  <options=bold>'.($report->plan->request->dryRun ? 'Planned changes' : 'Changed').'</>');
            $this->printLines($changes);
            $this->newLine();
        }

        if ($report->summary !== '') {
            $this->line('  <options=bold>The agent\'s summary</>');
            $this->printLines(explode("\n", $report->summary));
            $this->newLine();
        }

        $problems = $formatter->problems($report);

        if ($problems !== []) {
            $this->line('  <options=bold>Not done</>');
            $this->printLines($problems);
            $this->newLine();
        }

        if ($report->error !== null) {
            $this->components->error($report->error);
        }

        $this->components->info($formatter->nextStep($report));

        return $report->failed() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Raw output: these lines carry the agent's text and docs content, which
     * must never be read as console formatting tags.
     *
     * @param  list<string>  $lines
     */
    private function printLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->output->writeln('  '.$line, OutputInterface::OUTPUT_RAW);
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        // Artisan::call passes numbers as numbers; the console passes strings.
        return (is_string($value) || is_int($value)) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
