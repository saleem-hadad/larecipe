<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\DeletePageAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationReader;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationWriter;
use LaRecipe\Domains\Sources\Laravel\GitBranch;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;

uses(UsesDatabaseSets::class);

beforeEach(function () {
    $this->app->instance(GitBranch::class, new class('.') extends GitBranch
    {
        public function current(): ?string
        {
            return 'feature/billing-exports';
        }
    });
});

$page = fn (string $path) => app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));

/** Apply a write locally and record it in the branch's migration, the way the local MCP tools do. */
$session = function (string $path, string $body, ?string $basedOn = null) {
    $result = Fixtures::write('kb', $path, $body, ['title' => 'Exports']);
    $operation = $result->revision->basedOnHash === 'absent'
        ? DocsMigrationOperation::createPage($path, $result->revision->payload['frontmatter'], $body)
        : DocsMigrationOperation::updatePage($path, $result->revision->basedOnHash, $result->revision->payload['frontmatter'], $body);

    return app(DocsMigrationWriter::class)->record('kb', $operation, $result, Fixtures::agent(), "Write {$path}");
};

it('writes one readable migration per branch, marked as already run locally', function () use ($session) {
    $file = $session('billing/exports', "# Exports\n\nDownload invoices as CSV.\n");

    // The default set's file is {timestamp}_docs_{branch}.php; other sets add their key.
    expect(basename($file))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_docs_kb_feature_billing_exports\.php$/')
        ->and(app(DocsMigrationWriter::class)->nameFor('docs'))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_docs_feature_billing_exports$/')
        ->and(file_get_contents($file))->toContain("\$this->createPage('billing/exports', frontmatter: [")
        ->and(file_get_contents($file))->toContain("            Download invoices as CSV.\n")
        ->and(file_get_contents($file))->toContain("protected string \$author = 'claude-code';");

    $this->assertDatabaseHas('migrations', ['migration' => pathinfo($file, PATHINFO_FILENAME)]);
    $this->artisan('migrate')->expectsOutputToContain('Nothing to migrate')->assertSuccessful();
});

it('folds the session into the same file: create then update is one create', function () use ($session) {
    $first = $session('billing/exports', "# Exports\n");
    $second = $session('billing/exports', "# Exports\n\nNow with CSV.\n");
    $plan = app(DocsMigrationReader::class)->read($second);

    expect($second)->toBe($first)
        ->and($plan->operations)->toHaveCount(1)
        ->and($plan->operations[0]->type)->toBe(DocsMigrationOperationType::CreatePage)
        ->and($plan->operations[0]->body)->toBe("# Exports\n\nNow with CSV.\n")
        ->and($plan->summary)->toBe('Write billing/exports');
});

it('keeps the hash production had before the branch across several updates', function () use ($session, $page) {
    Fixtures::write('kb', 'faq', "# FAQ\n", ['title' => 'FAQ']);
    $original = $page('faq')->contentHash;

    $session('faq', "# FAQ\n\nOne.\n");
    $file = $session('faq', "# FAQ\n\nOne. Two.\n");
    $plan = app(DocsMigrationReader::class)->read($file);

    expect($plan->operations)->toHaveCount(1)
        ->and($plan->operations[0]->type)->toBe(DocsMigrationOperationType::UpdatePage)
        ->and($plan->operations[0]->basedOn)->toBe($original);
});

it('undoes the session with a local migrate:rollback, and the file applies again on a fresh run', function () use ($session, $page) {
    $session('billing/exports', "# Exports\n");
    $session('billing/exports', "# Exports v2\n");

    $this->artisan('migrate:rollback')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('billing/exports'))->toBeNull();

    // What a deploy does with the committed file.
    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('billing/exports')->body)->toBe("# Exports v2\n")
        ->and($page('billing/exports')->provenance->name)->toBe('claude-code');
});

it('drops the file when the session cancels itself out', function () use ($session) {
    $file = $session('scratch', "# Scratch\n");
    $result = app(DeletePageAction::class)->execute(new PageDeletion(
        new PageRef('kb', 'current', 'en', 'scratch'), Fixtures::agent(), Channel::PullRequest,
    ));

    $written = app(DocsMigrationWriter::class)->record('kb', DocsMigrationOperation::deletePage('scratch', $result->revision->basedOnHash), $result, Fixtures::agent());

    expect($written)->toBeNull()
        ->and(is_file($file))->toBeFalse();

    $this->assertDatabaseMissing('migrations', ['migration' => pathinfo($file, PATHINFO_FILENAME)]);
});
