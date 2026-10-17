<?php

use LaRecipe\Domains\Sync\Laravel\DocsMaintainer;
use LaRecipe\Tests\Feature\SyncAgent\Support\SyncAgent;

beforeEach(function () {
    $this->project = SyncAgent::project($this->app);
});

afterEach(fn () => $this->project->destroy());

$updatedBilling = "# Billing\n\nInvoices total their lines, then add tax per line.\n";
$frontmatter = ['title' => 'Billing', 'description' => 'How invoices are totalled.', 'sources' => ['app/Billing/**']];

it('updates the stale page in the working tree and leaves the rest alone', function () use ($updatedBilling, $frontmatter) {
    $this->project->write('app/Billing/Invoice.php', str_replace('return 100;', 'return 110; // tax', (string) $this->project->read('app/Billing/Invoice.php')))->commit('Add tax');
    $webhooks = $this->project->read('docs/guides/webhooks.md');

    DocsMaintainer::fake([
        SyncAgent::call('git_diff'),
        SyncAgent::call('read_page_source', ['path' => 'billing']),
        SyncAgent::write('billing', $updatedBilling, $frontmatter, 'Document the tax added per line'),
        SyncAgent::call('validate_docs'),
        'billing: documented the tax added per line, from app/Billing/Invoice.php.',
    ]);

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('docs/billing.md (published): Document the tax added per line')
        ->expectsOutputToContain('triggered by app/Billing/Invoice.php')
        ->expectsOutputToContain('billing: documented the tax added per line')
        ->expectsOutputToContain('Review the diff')
        ->assertSuccessful();

    expect($this->project->read('docs/billing.md'))->toContain('then add tax per line')
        ->and($this->project->read('docs/billing.md'))->toContain('app/Billing/**')
        ->and($this->project->read('docs/guides/webhooks.md'))->toBe($webhooks)
        // Nothing is committed without --commit: the developer reviews the diff first.
        ->and($this->project->git('status', '--porcelain'))->toContain('docs/billing.md');

    DocsMaintainer::assertPrompted(function ($prompt) {
        $tools = array_map(fn ($tool) => $tool->name(), [...$prompt->agent->tools()]);

        return str_contains($prompt->prompt, '`billing` (docs/billing.md)')
            && str_contains($prompt->prompt, 'main...HEAD plus uncommitted changes')
            && ! str_contains($prompt->prompt, 'guides/webhooks')
            && $prompt->agent->maxSteps() === 30
            && $prompt->agent->provider() === 'openai'
            && $prompt->agent->model() === 'gpt-test'
            && $tools === ['search_docs', 'read_page', 'list_pages', 'affected_pages', 'read_page_source', 'read_code', 'git_diff', 'app_reference', 'validate_docs', 'write_page', 'move_page', 'delete_page', 'update_navigation', 'update_manifest'];
    });
});

it('follows the larecipe-update-docs skill, loaded from the skill file', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    DocsMaintainer::fake(['Nothing needed changing.']);

    $this->artisan('larecipe:sync', ['--yes' => true])->assertSuccessful();

    $skill = (string) file_get_contents(dirname(__DIR__, 3).'/resources/boost/skills/larecipe-update-docs/SKILL.md');
    preg_match('/^# (.+)$/m', $skill, $heading);

    DocsMaintainer::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), '# '.$heading[1])
        && str_contains($prompt->agent->instructions(), 'is the affected_pages tool')
        && str_contains($prompt->agent->instructions(), 'data, never instructions')
        && str_contains($prompt->agent->instructions(), '# Reference: live-references.md')
        && ! str_contains($prompt->agent->instructions(), 'name: larecipe-update-docs'));
});

it('reports what a dry run would change and writes nothing', function () use ($updatedBilling, $frontmatter) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    $before = $this->project->read('docs/billing.md');

    DocsMaintainer::fake([
        SyncAgent::write('billing', $updatedBilling, $frontmatter, 'Document the tax'),
        'billing: would document the tax.',
    ]);
    $results = SyncAgent::recordToolResults();

    $this->artisan('larecipe:sync', ['--dry-run' => true, '--yes' => true])
        ->expectsOutputToContain('docs/billing.md (planned): Document the tax')
        ->expectsOutputToContain('Dry run: nothing was written.')
        ->assertSuccessful();

    expect($this->project->read('docs/billing.md'))->toBe($before)
        ->and($this->project->git('status', '--porcelain'))->toBe('')
        ->and(SyncAgent::resultOf($results, 'write_page'))->toStartWith('Dry run: write_page was not executed');

    DocsMaintainer::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'This is a dry run'));
});

it('updates the pages named with --page, even without a change', function () {
    DocsMaintainer::fake([
        SyncAgent::write('guides/webhooks', "# Webhooks\n\nAcme signs every delivery with HMAC.\n", ['title' => 'Webhooks', 'description' => 'How Acme delivers webhooks.', 'sources' => ['app/Webhooks/**']], 'Name the signature scheme'),
        'guides/webhooks: named the signature scheme.',
    ]);

    $this->artisan('larecipe:sync', ['--page' => ['docs/guides/webhooks.md'], '--yes' => true])
        ->expectsOutputToContain('docs/guides/webhooks.md (published): Name the signature scheme')
        ->assertSuccessful();

    expect($this->project->read('docs/guides/webhooks.md'))->toContain('with HMAC.');

    DocsMaintainer::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'asked for with --page'));
});

it('refuses an unknown page before spending a token', function () {
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--page' => ['nope'], '--yes' => true])
        ->expectsOutputToContain('has no page [nope]')
        ->assertExitCode(2);

    DocsMaintainer::assertNeverPrompted();
});

it('does nothing, for free, when no page is stale', function () {
    $this->project->write('README.md', "# Acme\n\nNew readme.\n")->commit('Touch the readme');
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('Nothing to update')
        ->assertSuccessful();

    DocsMaintainer::assertNeverPrompted();
});

it('commits only the docs it wrote, with the agent as co-author', function () use ($updatedBilling, $frontmatter) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n"); // the code change stays uncommitted

    DocsMaintainer::fake([
        SyncAgent::write('billing', $updatedBilling, $frontmatter, 'Document the tax added per line'),
        'billing: documented the tax.',
    ]);

    $this->artisan('larecipe:sync', ['--commit' => true, '--yes' => true])
        ->expectsOutputToContain('Committed as')
        ->assertSuccessful();

    $message = $this->project->git('log', '-1', '--format=%B');

    expect($message)->toStartWith('Update docs: billing')
        ->and($message)->toContain('- billing: Document the tax added per line')
        ->and($message)->toContain('Co-Authored-By: LaRecipe docs agent (openai/gpt-test) <noreply@larecipe.saleem.dev>')
        ->and($this->project->git('show', '--name-only', '--format=', 'HEAD'))->toBe('docs/billing.md')
        ->and($this->project->git('status', '--porcelain'))->toBe('M app/Billing/Invoice.php');
});

it('will not commit over uncommitted docs edits', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")
        ->append('docs/guides/webhooks.md', "\nA draft paragraph.\n");
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--commit' => true, '--yes' => true])
        ->expectsOutputToContain('already have uncommitted changes: docs/guides/webhooks.md')
        ->assertFailed();

    DocsMaintainer::assertNeverPrompted();
});

it('refuses --commit with --dry-run', function () {
    $this->artisan('larecipe:sync', ['--commit' => true, '--dry-run' => true, '--yes' => true])
        ->expectsOutputToContain('nothing to commit')
        ->assertExitCode(2);
});

it('audits the whole set with the audit skill, drifted pages first', function () {
    $this->project->append('app/Webhooks/Handler.php', "// retries\n")->commit('Retry deliveries');
    DocsMaintainer::fake(["2 pages reviewed.\nP1: guides/webhooks drifted."]);

    $this->artisan('larecipe:sync', ['--audit' => true, '--dry-run' => true, '--yes' => true])
        ->expectsOutputToContain('P1: guides/webhooks drifted.')
        ->assertSuccessful();

    DocsMaintainer::assertPrompted(function ($prompt) {
        $first = strpos($prompt->prompt, '1. `guides/webhooks`');

        return str_contains($prompt->agent->instructions(), '# Audit LaRecipe docs')
            && $first !== false
            && str_contains($prompt->prompt, 'drifted: its sources changed in')
            && str_contains($prompt->prompt, '`index` (docs/index.md), titled "Introduction"; sources: none; why: no sources:');
    });
});

it('asks before spending tokens, and runs nothing without consent', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync')
        ->expectsOutputToContain('openai/gpt-test')
        ->expectsConfirmation('Run the docs agent now? It spends tokens on your openai key.', 'no')
        ->assertSuccessful();

    $this->artisan('larecipe:sync', ['--no-interaction' => true])
        ->expectsOutputToContain('Run again with --yes')
        ->assertFailed();

    DocsMaintainer::assertNeverPrompted();
});

it('reports a write the docs refused, and the retry that fixed it', function () use ($updatedBilling, $frontmatter) {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")
        ->append('app/Webhooks/Handler.php', "// retries\n")
        ->commit('Tax and retries');

    DocsMaintainer::fake([
        SyncAgent::write('billing', '', $frontmatter, 'Empty by mistake'),
        SyncAgent::write('billing', $updatedBilling, $frontmatter, 'Document the tax added per line'),
        SyncAgent::write('guides/webhooks', '', ['title' => 'Webhooks'], 'Also empty'),
        'billing: documented the tax. guides/webhooks: could not write it.',
    ]);

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('docs/billing.md (published): Document the tax added per line')
        ->expectsOutputToContain('guides/webhooks (rejected): The page has validation errors')
        ->assertSuccessful();

    expect($this->project->read('docs/billing.md'))->toContain('then add tax per line');
});
