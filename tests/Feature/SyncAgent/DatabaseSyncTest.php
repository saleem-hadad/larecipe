<?php

use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListDraftsAction;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationReader;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Sources\Mcp\AuthorSession;
use LaRecipe\Domains\Sources\Mcp\WritePageTool;
use LaRecipe\Domains\Sync\Contracts\RemoteAuthorTools;
use LaRecipe\Domains\Sync\Laravel\DocsMaintainer;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;
use LaRecipe\Tests\Feature\SyncAgent\Support\SyncAgent;

uses(UsesDatabaseSets::class);

/**
 * The "kb" database set as the only set, with one page documenting
 * app/Billing/**, in the fixture app (a git repository unless $git is false).
 */
function databaseProject(bool $git = true, string $review = 'required'): TemporaryProject
{
    $kb = [...config('larecipe.sets.kb'), 'review' => $review];
    $project = SyncAgent::project(app(), $git, ['kb' => $kb], ['larecipe.default' => 'kb']);

    Fixtures::write('kb', 'billing', "# Billing\n\nInvoices total their lines.\n", ['title' => 'Billing', 'sources' => ['app/Billing/**']]);
    app()->forgetScopedInstances();

    return $project;
}

function kbPage(string $path): ?Page
{
    app()->forgetScopedInstances();

    return app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));
}

$fakeWrite = fn (string $summary = 'Document the tax') => DocsMaintainer::fake([
    SyncAgent::call('read_page_source', ['path' => 'billing']),
    SyncAgent::write('billing', "# Billing\n\nInvoices total their lines, then add tax.\n", ['title' => 'Billing', 'description' => 'How invoices are totalled.', 'sources' => ['app/Billing/**']], $summary),
    'billing: documented the tax.',
]);

afterEach(fn () => isset($this->project) ? $this->project->destroy() : null);

it('writes a database set change into the branch docs migration, inside a repository', function () use ($fakeWrite) {
    $this->project = databaseProject();
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    $fakeWrite();

    $this->artisan('larecipe:sync', ['--yes' => true])
        ->expectsOutputToContain('billing (published): Document the tax')
        ->expectsOutputToContain('recorded in database/migrations/docs/')
        ->expectsOutputToContain('Review the docs migration')
        ->assertSuccessful();

    $files = glob($this->project->path('database/migrations/docs').'/*_docs_feature.php');
    $plan = app(DocsMigrationReader::class)->read($files[0]);

    expect($files)->toHaveCount(1)
        ->and($plan->operations[0]->type->value)->toBe('update_page')
        ->and($plan->operations[0]->body)->toBe("# Billing\n\nInvoices total their lines, then add tax.\n")
        ->and($plan->author->name)->toBe('larecipe-sync')
        ->and(kbPage('billing')->body)->toContain('then add tax')
        ->and($this->project->git('status', '--porcelain', '--untracked-files=all'))->toContain('database/migrations/docs/');

    DocsMaintainer::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), "the branch's docs migration")
        && str_contains($prompt->agent->instructions(), '# Reference: database-sets.md'));
});

it('commits the docs migration with --commit', function () use ($fakeWrite) {
    $this->project = databaseProject();
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    $fakeWrite();

    $this->artisan('larecipe:sync', ['--commit' => true, '--yes' => true])->assertSuccessful();

    expect($this->project->git('show', '--name-only', '--format=', 'HEAD'))->toMatch('#^database/migrations/docs/\d{4}_\d{2}_\d{2}_\d{6}_docs_feature\.php$#')
        ->and($this->project->git('log', '-1', '--format=%B'))->toContain('Co-Authored-By: LaRecipe docs agent (openai/gpt-test)');
});

it('keeps changes made outside a repository as drafts under review: required', function () use ($fakeWrite) {
    $this->project = databaseProject(git: false);
    $fakeWrite();

    $this->artisan('larecipe:sync', ['--page' => ['billing'], '--yes' => true])
        ->expectsOutputToContain('billing (drafted): Document the tax')
        ->expectsOutputToContain('Publish the drafts in the admin UI')
        ->assertSuccessful();

    $drafts = app(ListDraftsAction::class)->execute('kb');

    expect($drafts)->toHaveCount(1)
        ->and($drafts[0]->channel)->toBe('sync')
        ->and($drafts[0]->author->name)->toBe('larecipe-sync')
        ->and($drafts[0]->summary)->toBe('Document the tax')
        ->and(kbPage('billing')->body)->toBe("# Billing\n\nInvoices total their lines.\n")
        ->and(is_dir($this->project->path('database/migrations/docs')))->toBeFalse();
});

it('publishes them at once under review: auto, still as attributed revisions', function () use ($fakeWrite) {
    $this->project = databaseProject(git: false, review: 'auto');
    $fakeWrite();

    $this->artisan('larecipe:sync', ['--page' => ['billing'], '--yes' => true])
        ->expectsOutputToContain('billing (published): Document the tax')
        ->expectsOutputToContain('Published as revisions (review: auto)')
        ->assertSuccessful();

    expect(app(ListDraftsAction::class)->execute('kb'))->toBe([])
        ->and(kbPage('billing')->body)->toContain('then add tax')
        ->and(kbPage('billing')->provenance->name)->toBe('larecipe-sync');
});

it('writes to a production set through its remote author tools, under its review mode', function () use ($fakeWrite) {
    $this->project = databaseProject();
    config()->set('larecipe.ai.sync.remote_token', 'remote-token-never-sent');

    // Stand in for the production endpoint: the same author tools, as the web server runs them.
    $remote = new class implements RemoteAuthorTools
    {
        public array $urls = [];

        public function tools(string $url): array
        {
            $this->urls[] = $url;

            return [app(WritePageTool::class)];
        }
    };
    app()->instance(RemoteAuthorTools::class, $remote);
    app()->bind(AuthorSession::class, fn ($app) => new AuthorSession($app, $app->make(SourceManager::class), $app->make(Authorizer::class), false));
    Gate::define('manageLarecipe', fn ($user = null) => true);
    $fakeWrite();

    $this->artisan('larecipe:sync', ['--remote' => 'https://acme.test/kb/mcp', '--page' => ['billing'], '--yes' => true])
        ->expectsOutputToContain('billing (drafted): Document the tax')
        ->expectsOutputToContain('Publish the drafts in the admin UI')
        ->assertSuccessful();

    $drafts = app(ListDraftsAction::class)->execute('kb');

    expect($remote->urls)->toBe(['https://acme.test/kb/mcp'])
        ->and($drafts)->toHaveCount(1)
        ->and($drafts[0]->channel)->toBe('remote_agent')
        ->and(is_dir($this->project->path('database/migrations/docs')))->toBeFalse();

    DocsMaintainer::assertPrompted(function ($prompt) {
        $names = array_map(fn ($tool) => $tool->name(), [...$prompt->agent->tools()]);

        return in_array('write_page', $names, true)
            && ! in_array('validate_docs', $names, true)
            && ! str_contains($prompt->agent->instructions().$prompt->prompt, 'remote-token-never-sent')
            && str_contains($prompt->agent->instructions(), 'remote author tools');
    });
});

it('explains what a remote run needs', function () {
    $this->project = databaseProject();
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--remote' => 'https://acme.test/kb/mcp', '--page' => ['billing'], '--yes' => true])
        ->expectsOutputToContain('Set LARECIPE_SYNC_TOKEN')
        ->assertFailed();

    config()->set('larecipe.ai.sync.remote_token', 'remote-token');

    $this->artisan('larecipe:sync', ['--remote' => 'not-a-url', '--page' => ['billing'], '--yes' => true])
        ->expectsOutputToContain('is not a URL')
        ->assertExitCode(2);

    DocsMaintainer::assertNeverPrompted();
});

it('never sends a repository set\'s changes to a remote server', function () {
    $this->project = SyncAgent::project(app(), config: ['larecipe.ai.sync.remote_token' => 'remote-token']);
    DocsMaintainer::fake();

    $this->artisan('larecipe:sync', ['--remote' => 'https://acme.test/docs/mcp', '--yes' => true])
        ->expectsOutputToContain('its changes belong in git')
        ->assertExitCode(2);

    DocsMaintainer::assertNeverPrompted();
});
