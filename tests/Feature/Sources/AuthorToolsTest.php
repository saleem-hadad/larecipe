<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\McpServiceProvider;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListDraftsAction;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationReader;
use LaRecipe\Domains\Sources\Laravel\GitBranch;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Sources\Mcp\AuthorSession;
use LaRecipe\Domains\Sources\Mcp\DeletePageTool;
use LaRecipe\Domains\Sources\Mcp\MovePageTool;
use LaRecipe\Domains\Sources\Mcp\UpdateManifestTool;
use LaRecipe\Domains\Sources\Mcp\UpdateNavigationTool;
use LaRecipe\Domains\Sources\Mcp\WritePageTool;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Sources\Support\AuthorToolsServer;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;

uses(UsesDatabaseSets::class);

beforeEach(function () {
    // Auto-discovered in a real app; Testbench loads only the providers a test asks for.
    $this->app->register(McpServiceProvider::class);

    config()->set('larecipe.sets.docs.source.path', Fixtures::copy('docs'));
    $this->app->forgetScopedInstances();
    $this->app->instance(GitBranch::class, new class('.') extends GitBranch
    {
        public function current(): ?string
        {
            return 'docs-for-exports';
        }
    });
});

/** Make the author tools behave as they do on the web endpoint (outside any pull request). */
$remote = function () {
    app()->bind(AuthorSession::class, fn ($app) => new AuthorSession($app, $app->make(SourceManager::class), $app->make(Authorizer::class), false));
};

$page = fn (string $set, string $path) => app(GetPageAction::class)->execute(new PageRef($set, 'current', 'en', $path));

it('registers the five author tools, destructive, with a set argument', function () {
    $tools = app(LaRecipe::class)->mcpTools();

    expect(array_keys($tools))->toContain(WritePageTool::class, MovePageTool::class, DeletePageTool::class, UpdateNavigationTool::class, UpdateManifestTool::class)
        ->and($tools[WritePageTool::class])->toBe(['author' => true, 'local' => false]);

    foreach ([WritePageTool::class, MovePageTool::class, DeletePageTool::class, UpdateNavigationTool::class, UpdateManifestTool::class] as $class) {
        $definition = app($class)->toArray();

        expect($definition['annotations']['destructiveHint'])->toBeTrue()
            ->and($definition['inputSchema']['properties'])->toHaveKey('set');
    }

    expect(app(WritePageTool::class)->toArray()['name'])->toBe('write_page')
        ->and(app(WritePageTool::class)->toArray()['inputSchema']['required'])->toBe(['path', 'body']);
});

it('writes a repository page into the working tree from the local server', function () use ($page) {
    AuthorToolsServer::tool(WritePageTool::class, [
        'path' => 'guides/retries',
        'frontmatter' => ['title' => 'Retries', 'description' => 'How Acme retries webhooks.'],
        'body' => "# Retries\n\nThree attempts.\n",
        'summary' => 'Document retries',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('outcome', 'published')
        ->where('docs_migration', null)
        ->where('revision.author.actor', 'agent')
        ->where('validation.ok', true)
        ->etc());

    expect(is_file(config('larecipe.sets.docs.source.path').'/guides/retries.md'))->toBeTrue()
        ->and($page('docs', 'guides/retries')->title)->toBe('Retries');
});

it('applies a database change locally and writes it into the branch docs migration', function () use ($page) {
    $response = AuthorToolsServer::tool(WritePageTool::class, [
        'set' => 'kb',
        'path' => 'billing/exports',
        'frontmatter' => ['title' => 'Exports', 'description' => 'CSV exports.'],
        'body' => "# Exports\n",
    ]);

    $file = glob(config('larecipe.migrations.path').'/*_docs_kb_docs_for_exports.php')[0] ?? null;

    $response->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('outcome', 'published')
        ->where('docs_migration', fn (string $path) => str_ends_with($path, basename((string) $file)))
        ->etc());

    AuthorToolsServer::tool(MovePageTool::class, ['set' => 'kb', 'from' => 'billing/exports', 'to' => 'billing/csv'])->assertOk();
    AuthorToolsServer::tool(UpdateNavigationTool::class, ['set' => 'kb', 'patch' => [['add' => 'billing/csv', 'group' => 'Billing']]])->assertOk();
    AuthorToolsServer::tool(UpdateManifestTool::class, ['set' => 'kb', 'patch' => ['name' => 'Knowledge base']])->assertOk();

    $plan = app(DocsMigrationReader::class)->read($file);

    expect($page('kb', 'billing/csv')->body)->toBe("# Exports\n")
        ->and(array_map(fn ($op) => $op->type->value, $plan->operations))->toBe(['create_page', 'update_navigation', 'update_manifest'])
        ->and($plan->operations[0]->target)->toBe('billing/csv')
        ->and($plan->operations[1]->tree)->toBe(['groups' => [['group' => 'Billing', 'pages' => ['billing/csv']]]])
        ->and($plan->author->name)->toBe('mcp-agent');

    $this->assertDatabaseHas('migrations', ['migration' => pathinfo($file, PATHINFO_FILENAME)]);
});

it('returns validation errors the agent can act on, and writes nothing', function () use ($page) {
    AuthorToolsServer::tool(WritePageTool::class, ['set' => 'kb', 'path' => 'empty', 'body' => ''])
        ->assertHasErrors(['validation errors'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('outcome', 'rejected')
            ->where('validation.issues.0.rule', 'page.empty')
            ->etc());

    expect($page('kb', 'empty'))->toBeNull()
        ->and(glob(config('larecipe.migrations.path').'/*.php'))->toBe([]);
});

it('refuses paths outside the set', function () {
    AuthorToolsServer::tool(WritePageTool::class, ['path' => '../../evil', 'body' => "# Evil\n"])
        ->assertHasErrors(['refused the path']);
});

it('never writes to a repository set from the web', function () use ($remote) {
    $remote();

    AuthorToolsServer::tool(WritePageTool::class, ['path' => 'x', 'body' => "# X\n"])
        ->assertHasErrors(['belong in git']);
});

it('requires the manageLarecipe gate on the web', function () use ($remote) {
    $remote();
    Gate::define('manageLarecipe', fn ($user = null) => false);

    AuthorToolsServer::tool(WritePageTool::class, ['set' => 'kb', 'path' => 'x', 'body' => "# X\n"])
        ->assertHasErrors(['manageLarecipe']);
});

it('keeps remote agent writes as drafts under review: required, without a docs migration', function () use ($remote, $page) {
    $remote();
    Gate::define('manageLarecipe', fn ($user = null) => true);

    AuthorToolsServer::tool(WritePageTool::class, ['set' => 'kb', 'path' => 'faq', 'frontmatter' => ['description' => 'FAQ'], 'body' => "# FAQ\n"])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('outcome', 'drafted')
            ->where('docs_migration', null)
            ->where('revision.channel', 'remote_agent')
            ->etc());

    expect($page('kb', 'faq'))->toBeNull()
        ->and(app(ListDraftsAction::class)->execute('kb'))->toHaveCount(1);
});

it('deletes pages and reports a missing one', function () use ($page) {
    Fixtures::write('kb', 'old', "# Old\n");

    AuthorToolsServer::tool(DeletePageTool::class, ['set' => 'kb', 'path' => 'old'])->assertOk();
    AuthorToolsServer::tool(DeletePageTool::class, ['set' => 'kb', 'path' => 'old'])->assertHasErrors(['no page']);

    expect($page('kb', 'old'))->toBeNull();
});
