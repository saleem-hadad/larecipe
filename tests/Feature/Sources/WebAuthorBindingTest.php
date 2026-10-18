<?php

use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Registrar;
use LaRecipe\Domains\Agents\Mcp\DocsServer;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListDraftsAction;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;
use LaRecipe\Tests\TestCase;

/*
 * Each set's web MCP endpoint is bound to that set (ARCHITECTURE.md 11.2 and
 * 22): a client connected to /kb/mcp can never read or write another set.
 */

uses(UsesDatabaseSets::class);

beforeEach(function () {
    $this->app->register(McpServiceProvider::class);

    $database = fn (string $prefix, array $extra = []) => array_replace_recursive([
        'route' => ['prefix' => $prefix, 'middleware' => ['web']],
        'source' => ['driver' => 'database', 'disk' => 'larecipe-assets'],
        'access' => ['mode' => 'public'],
    ], $extra);

    Sets::use([
        'docs' => [
            'route' => ['prefix' => 'docs', 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => TestCase::fixture('docs')],
            'access' => ['mode' => 'public'],
        ],
        'kb' => $database('kb', ['mcp' => ['web' => ['author_tools' => true]]]),
        'ops' => $database('ops', ['mcp' => ['web' => ['author_tools' => true]]]),
    ]);

    Fixtures::write('kb', 'faq', "# KB FAQ\n\nThe kb answer.\n");
    Fixtures::write('ops', 'faq', "# Ops FAQ\n\nThe ops runbook answer.\n");

    Gate::define('manageLarecipe', fn ($user = null) => $user?->id === 1);
    $this->actingAs(Sets::user(1));
});

$call = fn (string $endpoint, string $tool, array $arguments) => test()->postJson("/{$endpoint}", [
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments],
])->assertOk()->json('result');

$tools = fn (string $endpoint) => array_column(test()->postJson("/{$endpoint}", ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->json('result.tools'), 'name');

it('writes to the endpoint\'s own set when no set is given', function () use ($call) {
    $result = $call('kb/mcp', 'write_page', ['path' => 'news', 'body' => "# News\n"]);

    expect($result['isError'])->toBeFalse()
        ->and($result['structuredContent']['outcome'])->toBe('drafted')
        ->and(app(ListDraftsAction::class)->execute('kb'))->toHaveCount(1)
        ->and(app(ListDraftsAction::class)->execute('ops'))->toHaveCount(0);
});

it('refuses an author tool on /kb/mcp that names another set', function () use ($call) {
    foreach ([
        ['write_page', ['set' => 'ops', 'path' => 'pwned', 'body' => "# Pwned\n"]],
        ['delete_page', ['set' => 'ops', 'path' => 'faq']],
        ['move_page', ['set' => 'ops', 'from' => 'faq', 'to' => 'moved']],
        ['update_manifest', ['set' => 'ops', 'patch' => ['name' => 'Pwned']]],
    ] as [$tool, $arguments]) {
        $result = $call('kb/mcp', $tool, $arguments);

        expect($result['isError'])->toBeTrue($tool)
            ->and($result['structuredContent']['outcome'])->toBe('rejected')
            ->and($result['structuredContent']['message'])->toContain('only serves the [kb] docs');
    }

    expect(app(ListDraftsAction::class)->execute('ops'))->toHaveCount(0)
        ->and(app(GetPageAction::class)->execute(new PageRef('ops', 'current', 'en', 'faq')))->not->toBeNull();
});

it('accepts the endpoint\'s own set by name', function () use ($call) {
    $result = $call('kb/mcp', 'write_page', ['set' => 'kb', 'path' => 'news', 'body' => "# News\n"]);

    expect($result['isError'])->toBeFalse()
        ->and(app(ListDraftsAction::class)->execute('kb'))->toHaveCount(1);
});

it('scopes the read tools on /kb/mcp to kb, whatever set the caller names', function () use ($call) {
    $read = $call('kb/mcp', 'read_page', ['path' => 'faq', 'set' => 'ops']);
    expect($read['content'][0]['text'])->toContain('The kb answer')->not->toContain('ops runbook');

    $list = $call('kb/mcp', 'list_pages', ['set' => 'ops']);
    expect($list['structuredContent']['set'])->toBe('kb');

    $search = $call('kb/mcp', 'search_docs', ['query' => 'runbook', 'set' => 'ops']);
    expect($search['structuredContent']['set'])->toBe('kb')
        ->and($search['structuredContent']['results'])->toBe([]);
});

it('never offers affected_pages on a web endpoint', function () use ($tools) {
    expect($tools('kb/mcp'))->toContain('write_page')->not->toContain('affected_pages');
});

it('treats an HTTP call as a web call even when no endpoint binding ran', function () use ($tools) {
    // A host mounting the server by hand gets the safe, read-only web surface, never the local one.
    app(Registrar::class)->web('custom-mcp', DocsServer::class);
    app('router')->getRoutes()->refreshNameLookups();

    expect($tools('custom-mcp'))->toContain('read_page')
        ->not->toContain('write_page')
        ->not->toContain('affected_pages')
        ->not->toContain('validate_docs');
});
