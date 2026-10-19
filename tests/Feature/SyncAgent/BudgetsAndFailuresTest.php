<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use LaRecipe\Domains\Sync\DTOs\SyncRequest;
use LaRecipe\Domains\Sync\Laravel\DocsMaintainer;
use LaRecipe\Domains\Sync\Laravel\SyncDocsJob;
use LaRecipe\Domains\Sync\Laravel\SyncReadiness;
use LaRecipe\Tests\Feature\SyncAgent\Support\SyncAgent;

beforeEach(function () {
    $this->project = SyncAgent::project($this->app);
});

afterEach(fn () => $this->project->destroy());

$billing = fn (string $summary = 'Document the tax') => SyncAgent::write('billing', "# Billing\n\nInvoices add tax.\n", ['title' => 'Billing', 'description' => 'How invoices are totalled.', 'sources' => ['app/Billing/**']], $summary);
$webhooks = fn () => SyncAgent::write('guides/webhooks', "# Webhooks\n\nDeliveries retry.\n", ['title' => 'Webhooks', 'description' => 'How Acme delivers webhooks.', 'sources' => ['app/Webhooks/**']], 'Document retries');

it('stops at the page budget, in the plan and in the tools', function () use ($billing, $webhooks) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->append('app/Webhooks/Handler.php', "// retries\n")->commit('Tax and retries');
    $before = $this->project->read('docs/guides/webhooks.md');

    DocsMaintainer::fake([$billing(), $webhooks(), 'billing done; webhooks still needs an update.']);
    $results = SyncAgent::recordToolResults();

    $this->artisan('larecipe:sync', ['--max-pages' => 1, '--yes' => true])
        ->expectsOutputToContain('docs/billing.md (published)')
        ->expectsOutputToContain('The page budget (1) stopped the agent')
        ->expectsOutputToContain('docs/guides/webhooks.md (skipped): over the page budget')
        ->assertSuccessful();

    expect($this->project->read('docs/billing.md'))->toContain('Invoices add tax.')
        ->and($this->project->read('docs/guides/webhooks.md'))->toBe($before)
        ->and(SyncAgent::resultOf($results, 'write_page', 1))->toStartWith('Not done: this run may change at most 1 page');

    DocsMaintainer::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '`billing`') && ! str_contains($prompt->prompt, '`guides/webhooks`'));
});

it('stops at the step budget', function () use ($billing) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    $before = $this->project->read('docs/billing.md');

    DocsMaintainer::fake([
        SyncAgent::call('git_diff'),
        $billing(),
        'Never reached.',
    ]);

    $this->artisan('larecipe:sync', ['--max-steps' => 2, '--yes' => true])
        ->expectsOutputToContain('used its whole step budget (2 steps)')
        ->assertSuccessful();

    // The last step's tool call is not run once the budget is spent.
    expect($this->project->read('docs/billing.md'))->toBe($before);

    DocsMaintainer::assertPrompted(fn ($prompt) => $prompt->agent->maxSteps() === 2);
});

it('explains how to install laravel/ai when it is missing', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    app()->bind(SyncReadiness::class, fn ($app) => new SyncReadiness($app['config'], fn () => false));
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('through laravel/ai, which is not installed')
        ->expectsOutputToContain('composer require laravel/ai')
        ->expectsOutputToContain('larecipe-update-docs skill, at no extra cost')
        ->assertFailed();

    DocsMaintainer::assertNeverPrompted();
});

it('explains a missing provider key or an unknown provider', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    config()->set('ai.providers.openai.key', null);
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('The [openai] provider has no API key. Set OPENAI_API_KEY in .env')
        ->assertFailed();

    $this->artisan('larecipe:sync', ['--provider' => 'nope', '--yes' => true])
        ->expectsOutputToContain('There is no [nope] provider in config/ai.php.')
        ->assertFailed();

    DocsMaintainer::assertNeverPrompted();
});

it('reports a provider error and keeps what was already written', function () use ($billing) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    $step = 0;

    DocsMaintainer::fake(function () use (&$step, $billing) {
        if ($step++ === 0) {
            return $billing();
        }

        throw new RuntimeException('429 Too Many Requests: rate limited by the provider');
    });

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('docs/billing.md (published): Document the tax')
        ->expectsOutputToContain('The run stopped: 429 Too Many Requests')
        ->assertFailed();

    expect($this->project->read('docs/billing.md'))->toContain('Invoices add tax.');
});

it('queues a run as an ordinary job, and the job runs it', function () use ($billing) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    config()->set('larecipe.ai.sync.queue', 'docs');
    Queue::fake();

    $this->artisan('larecipe:sync', ['--queue' => true, '--dry-run' => true, '--yes' => true])
        ->expectsOutputToContain('Queued the docs agent on the [docs] queue')
        ->assertSuccessful();

    Queue::assertPushedOn('docs', SyncDocsJob::class, fn (SyncDocsJob $job) => $job->request['dry_run'] === true
        && $job->tags() === ['larecipe', 'larecipe:sync', 'set:default']);
    DocsMaintainer::assertNeverPrompted();

    DocsMaintainer::fake([$billing(), 'billing: documented the tax.']);
    Log::spy();

    app()->call([new SyncDocsJob((new SyncRequest(maxPages: 5, maxSteps: 10))->toArray()), 'handle']);

    expect($this->project->read('docs/billing.md'))->toContain('Invoices add tax.');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'docs/billing.md (published): Document the tax')
        && str_contains($message, 'Review the diff'));
});

it('is never part of the CI scaffolds', function () {
    foreach (glob(dirname(__DIR__, 3).'/stubs/{ci/*,*.yml}', GLOB_BRACE) as $stub) {
        expect((string) file_get_contents($stub))->not->toContain('larecipe:sync');
    }
});
