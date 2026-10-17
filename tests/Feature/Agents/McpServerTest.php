<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Testing\TestListResponse;
use LaRecipe\Domains\Agents\Mcp\DocsServer;
use LaRecipe\Domains\Agents\Mcp\McpScope;
use LaRecipe\Domains\Agents\Mcp\Prompts\DocumentChangePrompt;
use LaRecipe\Domains\Agents\Mcp\Prompts\WriteDocsPagePrompt;
use LaRecipe\Domains\Agents\Mcp\Resources\DocPageResource;
use LaRecipe\Domains\Agents\Mcp\Tools\ListPagesTool;
use LaRecipe\Domains\Agents\Mcp\Tools\ReadPageTool;
use LaRecipe\Domains\Agents\Mcp\Tools\SearchDocsTool;
use LaRecipe\Domains\Agents\Mcp\Tools\SubmitFeedbackTool;
use LaRecipe\Domains\Delivery\Events\FeedbackSubmitted;
use LaRecipe\Domains\Delivery\Events\McpToolCalled;
use LaRecipe\Domains\Delivery\Events\SearchPerformed;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Validation\Mcp\ValidateDocsTool;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Agents\Support\FakeAuthorTool;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

beforeEach(fn () => Sets::withBilling());

/** One JSON-RPC call to a set's web endpoint, the way an MCP client sends it. */
function mcpCall(string $prefix, string $method, array $params = [], int $status = 200): array
{
    return test()->postJson("/{$prefix}/mcp", ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])
        ->assertStatus($status)
        ->json();
}

/** laravel/mcp's assertRegistered() builds tools with `new`, so tools with dependencies are checked by name. */
function toolNames(TestListResponse $list): array
{
    return (fn () => array_column($this->items, 'name'))->call($list);
}

it('registers the local stdio server and the larecipe:mcp command', function () {
    expect(app(Registrar::class)->getLocalServer('larecipe'))->not->toBeNull()
        ->and(array_keys(Artisan::all()))->toContain('larecipe:mcp');
});

it('exposes the read tools, feedback and validation on the local server', function () {
    expect(toolNames(DocsServer::tools()))->toBe(['search_docs', 'read_page', 'list_pages', 'submit_feedback', 'validate_docs']);

    DocsServer::tool(SearchDocsTool::class, ['query' => 'x'])->assertOk();
});

it('searches the caller\'s visible pages', function () {
    Event::fake([SearchPerformed::class, McpToolCalled::class]);

    DocsServer::tool(SearchDocsTool::class, ['query' => 'invoices', 'set' => 'billing'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('set', 'billing')->where('query', 'invoices')->has('version')->has('results', fn ($results) => $results->each(fn ($result) => $result->hasAll(['title', 'path', 'url', 'markdown_url', 'heading', 'snippet', 'description', 'score']))))
        ->assertSee('guides/invoices');

    DocsServer::tool(SearchDocsTool::class, ['query' => 'roadmap', 'set' => 'billing'])->assertDontSee('roadmap.md');

    Event::assertDispatched(SearchPerformed::class, fn (SearchPerformed $event) => $event->set === 'billing' && $event->source === 'mcp' && $event->kind === 'agent');
    Event::assertDispatched(SearchPerformed::class, fn (SearchPerformed $event) => $event->query === 'roadmap' && $event->zeroResults);
    Event::assertDispatched(McpToolCalled::class, fn (McpToolCalled $event) => $event->tool === 'search_docs' && $event->transport === 'local' && $event->arguments['query'] === 'invoices');
});

it('reads a page as its agent Markdown, by path, URL or anchor, and one section of it', function () {
    DocsServer::tool(ReadPageTool::class, ['path' => 'installation', 'set' => 'billing'])
        ->assertOk()
        ->assertSee(['title: Installation', 'composer require acme/billing', '## Configure webhooks']);

    DocsServer::tool(ReadPageTool::class, ['path' => Sets::url('billing/guides/subscriptions.md'), 'set' => 'billing'])
        ->assertOk()
        ->assertSee('Subscriptions renew at the end of each period.');

    DocsServer::tool(ReadPageTool::class, ['path' => 'guides/subscriptions', 'section' => 'renewals', 'set' => 'billing'])
        ->assertOk()
        ->assertSee(['title: Subscriptions', '## Renewals'])
        ->assertDontSee(['## Cancelling', 'Create a subscription for a customer.']);

    DocsServer::tool(ReadPageTool::class, ['path' => '/billing/guides/subscriptions#cancelling', 'set' => 'billing'])
        ->assertOk()
        ->assertSee('## Cancelling')
        ->assertDontSee('## Renewals');

    DocsServer::tool(ReadPageTool::class, ['path' => 'installation', 'section' => 'nope', 'set' => 'billing'])
        ->assertHasErrors(['has no section "nope"', 'Requirements']);
});

it('treats hidden pages as missing, everywhere', function () {
    DocsServer::tool(ReadPageTool::class, ['path' => 'guides/advanced/roadmap', 'set' => 'billing'])
        ->assertHasErrors(['There is no page at "guides/advanced/roadmap"']);

    Gate::define('viewLarecipe', fn ($user = null) => $user !== null);

    DocsServer::actingAs(Sets::user())->tool(ReadPageTool::class, ['path' => 'guides/advanced/roadmap', 'set' => 'billing'])
        ->assertOk()
        ->assertSee('Usage-based pricing ships next quarter.');
});

it('lists the sets first on the local server, then a set\'s navigation tree', function () {
    DocsServer::tool(ListPagesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('sets', 3)->where('sets.0.set', 'docs')->where('sets.0.default', true)->etc());

    DocsServer::tool(ListPagesTool::class, ['set' => 'billing'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('set', 'billing')
            ->where('pages.0.title', 'Introduction')
            ->where('pages.0.path', 'index')
            ->where('pages.0.description', 'What Billing does and where to start.')
            ->where('pages.2.type', 'group')
            ->where('pages.2.label', 'Guides')
            ->where('pages.2.items.2.label', 'Advanced')
            ->where('pages.4.href', 'https://status.example.com')
            ->where('other_pages.0.path', 'changelog')
            ->etc());

    DocsServer::tool(ListPagesTool::class, ['set' => 'billing', 'group' => 'advanced'])
        ->assertOk()
        ->assertSee('guides/advanced/proration')
        ->assertDontSee('guides/invoices');

    DocsServer::tool(ListPagesTool::class, ['set' => 'billing', 'group' => 'Nope'])->assertHasErrors(['Groups: Guides; Advanced; Reference']);
    DocsServer::tool(ListPagesTool::class, ['set' => 'nope'])->assertHasErrors(['There is no doc set named "nope"']);
});

it('sends agent feedback as the same event the page widget fires', function () {
    Event::fake([FeedbackSubmitted::class]);

    DocsServer::tool(SubmitFeedbackTool::class, ['path' => 'installation', 'helpful' => false, 'comment' => 'The webhook route name is wrong.', 'set' => 'billing'])
        ->assertOk()
        ->assertSee('Your feedback on "Installation" was sent');

    DocsServer::tool(SubmitFeedbackTool::class, ['path' => 'guides/advanced/roadmap', 'helpful' => true, 'set' => 'billing'])->assertHasErrors();

    Event::assertDispatchedTimes(FeedbackSubmitted::class, 1);
    Event::assertDispatched(FeedbackSubmitted::class, fn (FeedbackSubmitted $event) => $event->set === 'billing' && $event->page->path === 'installation' && $event->helpful === false && $event->source === 'agent' && $event->kind === 'agent' && $event->comment === 'The webhook route name is wrong.');
});

it('validates a set on request', function () {
    DocsServer::tool(ValidateDocsTool::class, ['set' => 'billing'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('ok', false)->where('summary.errors', 1)->where('issues.0.rule', 'manifest.schema')->etc());

    DocsServer::tool(ValidateDocsTool::class, ['set' => 'handbook', 'version' => '9.x'])->assertHasErrors(['no version "9.x"']);
});

it('serves pages as docs:// resources, nested paths included', function () {
    DocsServer::resource(DocPageResource::class, ['set' => 'billing', 'version' => 'current', 'path' => 'installation'])
        ->assertOk()
        ->assertSee('composer require acme/billing');

    $result = mcpCall('billing', 'resources/read', ['uri' => 'docs://billing/current/guides/advanced/proration']);

    expect($result['result']['contents'][0]['text'])->toContain('Changing plans mid-period charges the difference.')
        ->and($result['result']['contents'][0]['mimeType'])->toBe('text/markdown');

    expect(mcpCall('billing', 'resources/templates/list')['result']['resourceTemplates'][0]['uriTemplate'])->toBe('docs://{set}/{version}/{path}');
});

it('offers the write-docs-page and document-change prompts', function () {
    DocsServer::prompt(WriteDocsPagePrompt::class, ['topic' => 'refunds', 'path' => 'billing/refunds'])
        ->assertOk()
        ->assertSee(['Write a docs page about refunds at "billing/refunds"', 'Search first', 'validate_docs']);

    DocsServer::prompt(DocumentChangePrompt::class, ['change' => 'Renamed the subscribe() method'])
        ->assertOk()
        ->assertSee(['Renamed the subscribe() method', 'larecipe:affected']);
});

it('tells clients the workflow: search first, read before writing, validate after writing', function () {
    $result = mcpCall('docs', 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']]);

    expect($result['result']['instructions'])->toContain('Search first')->toContain('Read before you write')->toContain('Validate after you write')
        ->and($result['result']['serverInfo']['name'])->toBe('Acme Docs');
});

it('binds each web endpoint to its set', function () {
    $tools = mcpCall('billing', 'tools/list')['result']['tools'];
    $names = array_column($tools, 'name');
    $search = collect($tools)->firstWhere('name', 'search_docs');

    expect($names)->toBe(['search_docs', 'read_page', 'list_pages', 'submit_feedback'])
        ->and($search['inputSchema']['properties'])->not->toHaveKey('set')
        ->and($search['annotations']['readOnlyHint'])->toBeTrue();

    $result = mcpCall('billing', 'tools/call', ['name' => 'read_page', 'arguments' => ['path' => 'installation', 'set' => 'docs']]);
    expect($result['result']['content'][0]['text'])->toContain('acme/billing');

    $result = mcpCall('billing', 'tools/call', ['name' => 'list_pages', 'arguments' => []]);
    expect($result['result']['structuredContent']['set'])->toBe('billing');

    $result = mcpCall('docs', 'resources/read', ['uri' => 'docs://billing/current/installation'], 500);
    expect($result['error']['message'] ?? '')->toContain('no doc set named "billing" here');
});

it('re-checks access on every call of a private set\'s endpoint', function () {
    Gate::define('viewLarecipe', fn ($user = null) => $user?->id === 7);
    Sets::withBilling(['access' => ['mode' => 'gate']]);

    $result = mcpCall('billing', 'tools/call', ['name' => 'read_page', 'arguments' => ['path' => 'installation']]);
    expect($result['result']['isError'])->toBeTrue()
        ->and($result['result']['content'][0]['text'])->toBe('These docs are not available.');

    $this->actingAs(Sets::user(7));
    $result = mcpCall('billing', 'tools/call', ['name' => 'read_page', 'arguments' => ['path' => 'installation']]);
    expect($result['result']['isError'])->toBeFalse();
});

it('composes author tools: always local, on the web only for database sets that allow them', function () {
    app(LaRecipe::class)->mcpTool(FakeAuthorTool::class, author: true);
    Gate::define('manageLarecipe', fn ($user = null) => $user?->id === 1);

    expect(toolNames(DocsServer::tools()))->toContain('fake_write_page');

    // A repository set never exposes author tools on the web, even to a manager.
    app(McpScope::class)->bindWeb('billing');
    expect(toolNames(DocsServer::actingAs(Sets::user())->tools()))->not->toContain('fake_write_page')->toContain('validate_docs');

    // A database set with author_tools on: only a signed-in manager gets them.
    Sets::withBilling(['source' => ['driver' => 'database'], 'mcp' => ['web' => ['author_tools' => true]]]);
    app(SourceManager::class)->extend('database', fn ($app, $set) => new RepositorySource($set->key, Sets::fixture('billing')));
    app(McpScope::class)->bindWeb('billing');

    auth()->forgetGuards();
    expect(toolNames(DocsServer::tools()))->not->toContain('fake_write_page')->not->toContain('validate_docs');
    expect(toolNames(DocsServer::actingAs(Sets::user(2))->tools()))->not->toContain('fake_write_page');
    expect(toolNames(DocsServer::actingAs(Sets::user())->tools()))->toContain('fake_write_page')->toContain('validate_docs');
    DocsServer::actingAs(Sets::user())->tool(FakeAuthorTool::class, ['set' => 'docs'])->assertSee('wrote to billing');
});

it('reports web calls with the web transport', function () {
    Event::fake([McpToolCalled::class]);

    mcpCall('billing', 'tools/call', ['name' => 'read_page', 'arguments' => ['path' => 'installation']]);

    Event::assertDispatched(McpToolCalled::class, fn (McpToolCalled $event) => $event->set === 'billing' && $event->transport === 'web' && $event->page?->path === 'installation' && $event->kind === 'agent');
});

it('answers GET on the endpoint with 405, as the stateless protocol expects', function () {
    $this->get('/billing/mcp')->assertStatus(405)->assertHeader('Allow', 'POST');
});
