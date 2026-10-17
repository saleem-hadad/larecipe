# LaRecipe v3: AI Tooling Research

Research date: 2026-10-17. Facts were checked against official docs, GitHub sources and Packagist, and against vendor source in `/Users/saleemhadad/Code/nashra-app/vendor` (Laravel 12.69.3, laravel/ai v1.0.1, laravel/mcp v1.0.1, laravel/boost v2.10.1, inertiajs/inertia-laravel v3.0.6, league/commonmark 2.10.3). Anything not confirmed from a primary source is marked "unverified". Vendor paths are relative to `nashra-app/vendor/` unless stated.

## 0. Decision-relevant summary

**Platform floor**
- Laravel 13 is current (v13.37.0, 2026-10-16) and needs PHP ^8.3.
- laravel/mcp v1.0.2 effectively needs Laravel 12.41.1+ (through `illuminate/json-schema`).
- laravel/ai v1.2.3 needs PHP ^8.3 and Laravel 12.62+ or 13.15+.
- A sensible v3 floor is **PHP 8.3, Laravel 12.62+ or 13.x**.

**laravel/mcp v1.0**
- A package registers servers from its own service provider:
  - `Mcp::local('larecipe', Server::class)` for stdio, launched with `php artisan mcp:start larecipe`. This is the same pattern Boost uses.
  - `Mcp::web('docs/mcp', Server::class)` for HTTP (POST only), placed in a routes file loaded with `loadRoutesFrom()` so route caching works.
- Tools are classes extending `Laravel\Mcp\Server\Tool` with `schema(JsonSchema)`, `outputSchema(JsonSchema)` and `handle(Request): Response`. `Response::structured([...])` returns structured content.
- Protocol 2026-07-28 is stateless. Older initialize-based clients (2025-11-25 and 2025-06-18) are still served.
- New in 1.0: MCP Apps (`AppResource`), `ToolSearch`, cache hints, and an MCP client.

**laravel/ai**
- A laravel/mcp `Tool` instance can be passed directly as an agent tool (`McpServerTool` wraps it). One tool class can therefore power both the MCP server and an "Ask the docs" chat.
- Streaming to `useChat` works via `Vercel::chat($request)` and `->usingVercelDataProtocol()` (`x-vercel-ai-ui-message-stream: v1`). AG-UI is also supported.
- Vector queries (`whereVectorSimilarTo`) are **Postgres/pgvector only**. LaRecipe needs a keyword-search fallback.

**Boost**
- A package ships guidance in two places, with no registration needed:
  - Guidelines: `resources/boost/guidelines/*.md|*.blade.php` (start with `core.blade.php`).
  - Skills: `resources/boost/skills/{name}/SKILL.md`.
- Boost only picks these up for **direct**, non-first-party dependencies, and the user must select the package during `boost:install`.
- Boost 2.10 writes `AGENTS.md` for every agent except Claude Code, which gets `CLAUDE.md` if one exists. Skills go to per-agent folders (`.claude/skills`, `.agents/skills`, `.github/skills`, `.cursor/skills`, ...).
- `@scoped(['resources/docs/**'])` blocks give path-scoped rules.

**Claude Code**
- Skills follow the agentskills.io standard. For portability, use only `name`, `description`, `license`, `compatibility`, `metadata` and `allowed-tools` in the frontmatter.
- There are 33 hook events. A Stop hook can return `decision: "block"` or `hookSpecificOutput.additionalContext` to make Claude keep working. `stop_hook_active` prevents loops.
- Plugins need only `.claude-plugin/plugin.json` (just `name` required). The package repo can double as a marketplace via `.claude-plugin/marketplace.json`.
- **claude-code-action rejects `push` events.** Use `pull_request: closed` with a merged check, `workflow_run`, `schedule` or `workflow_dispatch`. Let Claude edit files only, and open the PR with `peter-evans/create-pull-request`.

**Cross-agent**
- `.agents/skills/` plus `.claude/skills/` covers Claude Code, Codex, Cursor, Copilot and Gemini CLI.
- llms.txt v2 (2026-08) adds path-scoped files, per-page `.md`, and `Link: rel="alternate"; type="text/markdown"` / `rel="describedby"`. `llms-full.txt` is a convention, not part of the spec.
- Serve `.md` URLs plus `Accept: text/markdown` with `Vary: Accept`. Vercel, Stripe, Mintlify and Cloudflare all do this.
- The de facto docs-MCP surface is two tools: `search` (query, optional version) and `read`/`query-docs` returning Markdown.

**UI**
- Do NOT build public docs pages on Inertia in a package:
  - Inertia's global singleton state leaks the host's shared props into package pages.
  - SSR is on by default in v3 and would hit the host's SSR server.
  - AI crawlers do not run JS.
- Instead, render the article HTML in PHP, mount React islands (`data-component` / `data-props`), and serve assets from vendor through a cached route (Livewire pattern) with optional publishing.

**Markdown**
- Use league/commonmark ^2.10.3 (security fixes, plus correct handling of uppercase inline tags) and Phiki v2 for highlighting (PHP 8.2+).
- Support both GitHub alerts and custom `:::` directives (a `BlockStartParser` at priority 80).
- PascalCase `<Callout>` tags need a custom parser at priority above 40 to avoid enabling raw HTML.
- For agents, render components back into portable Markdown.

**Docs sync**
- Use a deterministic core: `sources:` frontmatter plus a `larecipe:affected` diff-to-pages command.
- Layer agent guidance, then in-session Stop/SessionStart hooks (Claude and Codex share the semantics), then deterministic git hooks, then CI. In CI, run a no-AI check on PRs and a post-merge agent that opens a docs PR.

---

## 1. laravel/mcp (Laravel MCP)

### Versions and requirements (verified)

| Item | Value | Source |
| --- | --- | --- |
| Latest release | `v1.0.2` (2026-10-11); v1.0.0 shipped 2026-09-14 | https://repo.packagist.org/p2/laravel/mcp.json , https://github.com/laravel/mcp/releases |
| Installed in nashra-app | `v1.0.1` | `nashra-app/vendor/composer/installed.json` |
| PHP | `^8.2` (+ `ext-json`, `ext-mbstring`) | `vendor/laravel/mcp/composer.json` |
| Laravel | `illuminate/* ^11.45.3\|^12.41.1\|^13.0` BUT also `illuminate/json-schema ^12.41.1\|^13.0`, and `illuminate/json-schema` starts at v12.28. So effectively **Laravel 12.41.1+ or 13.x**. | same, plus https://repo.packagist.org/p2/illuminate/json-schema.json |
| Auto-discovered provider | `Laravel\Mcp\Server\McpServiceProvider`, facade alias `Mcp` => `Laravel\Mcp\Facades\Mcp` | `composer.json` `extra.laravel` |
| MCP protocol | Server advertises `2026-07-28` (stateless, `server/discover` handshake). Legacy `initialize` still served for `2025-11-25` and `2025-06-18` clients. | `src/Enums/ProtocolVersion.php`, `src/Server/Methods/Initialize.php`, `src/Server/Methods/Discover.php` |
| Laravel docs | 13.x is current: https://laravel.com/framework/docs/13.x/mcp (12.x page: https://laravel.com/docs/12.x/mcp) | |

### Registration API (`Laravel\Mcp\Server\Registrar`, via `Mcp` facade)

From `vendor/laravel/mcp/src/Server/Registrar.php`:

```php
public function web(string $route, string $serverClass): \Illuminate\Routing\Route;   // HTTP, POST only
public function local(string $handle, string $serverClass): void;                     // stdio, via `php artisan mcp:start {handle}`
public function oauthRoutes(string $oauthPrefix = 'oauth'): void;                      // .well-known + /oauth/register (Passport)
public function registerClient(string $name, Closure $factory): void;                  // MCP *client* (new in 1.0)
public function client(string $name): \Laravel\Mcp\Client;
public function oAuthRoutesFor(string $client, Closure|array $handler, array|string $middleware = 'web', ...): void;
```

Details that matter for a package:

- `Mcp::web()` registers `POST $route` with middleware `ReorderJsonAccept`, `ValidateMcpHeaders`, `AddWwwAuthenticateHeader`, and registers `GET`/`DELETE $route` that return `405 Allow: POST` (no GET SSE listening stream; the 2026-07-28 protocol is stateless). It returns the `Route` so you can chain `->middleware()` / `->name()`.
- Streaming: if a tool's `handle()` returns a generator, the HTTP transport switches the POST response to `Content-Type: text/event-stream` with `X-Accel-Buffering: no` (`src/Server/Transport/HttpTransport.php`).
- The package's own provider only auto-loads `base_path('routes/ai.php')` (published with `--tag=ai-routes`) and skips it when routes are cached (`McpServiceProvider::registerRoutes`). A third-party package therefore should NOT rely on the app's `routes/ai.php`; register in its own service provider.
- `Mcp::local()` is not a route, it just stores a closure in the registrar. `mcp:start` is only registered when running in console.
- **Precedent for packages:** Laravel Boost registers its local server from its provider: `Mcp::local('laravel-boost', Boost::class);` in `BoostServiceProvider::boot()` (`vendor/laravel/boost/src/BoostServiceProvider.php:68`), and agents launch it with `php artisan boost:mcp` (a thin wrapper command). The generic equivalent is `php artisan mcp:start larecipe`.
- Other artisan commands: `make:mcp-server`, `make:mcp-tool`, `make:mcp-resource`, `make:mcp-prompt`, `make:mcp-app-resource`, `mcp:inspector {handle-or-route}`, `mcp:start {handle}` (`src/Server/McpServiceProvider.php::registerCommands`).
- Config file `config/mcp.php` keys: `redirect_domains`, `custom_schemes` (e.g. `claude`, `cursor`, `vscode`), `authorization_server`, `tool_search.max_tool_calls`, `tool_search.max_output_bytes` (`vendor/laravel/mcp/config/mcp.php`).

### Recommended package registration (LaRecipe)

```php
// packages/larecipe/src/LaRecipeServiceProvider.php
use Laravel\Mcp\Facades\Mcp;

public function boot(): void
{
    if (! class_exists(Mcp::class)) {
        return; // laravel/mcp not installed (if kept as a "suggest" dependency)
    }

    // 1) Local stdio server for coding agents in the user's repo:
    //    .mcp.json => { "command": "php", "args": ["artisan", "mcp:start", "larecipe"] }
    Mcp::local('larecipe', \LaRecipe\Mcp\DocsServer::class);

    // 2) Public HTTP server for remote agents reading the hosted docs.
    //    loadRoutesFrom() is skipped automatically when routes are cached,
    //    and route:cache picks it up because the provider boots during caching.
    if (config('larecipe.mcp.web.enabled', true)) {
        $this->loadRoutesFrom(__DIR__.'/../routes/mcp.php');
    }
}
```

```php
// packages/larecipe/routes/mcp.php
use Laravel\Mcp\Facades\Mcp;

Mcp::web(config('larecipe.mcp.web.path', 'docs/mcp'), \LaRecipe\Mcp\DocsServer::class)
    ->middleware(config('larecipe.mcp.web.middleware', ['throttle:60,1']))
    ->name('larecipe.mcp');
```

### Minimal Server (current API)

Attributes are read via `resolveAttribute()` in `Laravel\Mcp\Server::createContext()`; properties `$name`, `$version`, `$instructions` are the fallback.

```php
namespace LaRecipe\Mcp;

use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('LaRecipe Docs')]
#[Version('3.0.0')]
#[Instructions('Search and read this project\'s documentation. Call search_docs first, then read_page with a returned slug.')]
#[Cacheable(ttlMs: 60_000, scope: CacheScope::Public)]   // cache hints on discover/list/read (1.0+)
class DocsServer extends Server
{
    protected array $tools = [
        Tools\SearchDocs::class,
        Tools\ReadPage::class,
        // Large catalogs: expose only search_tools + execute_tools
        // \Laravel\Mcp\Server\Tools\ToolSearch::class => [Tools\ListVersions::class, ...],
    ];

    protected array $resources = [
        Resources\DocPage::class,      // resource template docs://{version}/{slug}
    ];

    protected array $prompts = [
        Prompts\WriteDocsPage::class,
    ];
}
```

### Minimal Tool with input + output schema

Base class `Laravel\Mcp\Server\Tool` (`src/Server/Tool.php`): `schema(JsonSchema $schema): array`, `outputSchema(JsonSchema $schema): array`, `handle(...)` resolved through the container (constructor and method DI). Name defaults to kebab-case of the class unless `#[Name]` / `$name` set. Annotations: `#[IsReadOnly]`, `#[IsIdempotent]`, `#[IsDestructive]`, `#[IsOpenWorld]` (`src/Server/Tools/Annotations`). Optional `shouldRegister(...)` method for conditional registration (`Primitive::eligibleForRegistration`).

```php
namespace LaRecipe\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LaRecipe\Search\DocsIndex;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_docs')]
#[Title('Search documentation')]
#[Description('Full-text + semantic search over the docs for a given version. Returns slugs, titles and snippets.')]
#[IsReadOnly]
class SearchDocs extends Tool
{
    public function __construct(private DocsIndex $index) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'   => $schema->string()->description('What to look for.')->required(),
            'version' => $schema->string()->description('Docs version, e.g. "3.x". Defaults to the latest.'),
            'limit'   => $schema->integer()->min(1)->max(20)->default(5),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()->items($schema->object(fn ($s) => [
                'slug'    => $s->string()->required(),
                'title'   => $s->string()->required(),
                'url'     => $s->string()->required(),
                'snippet' => $s->string()->required(),
            ]))->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([           // Laravel validator; errors go back to the client
            'query'   => ['required', 'string', 'max:500'],
            'version' => ['nullable', 'string'],
            'limit'   => ['nullable', 'integer', 'between:1,20'],
        ]);

        $results = $this->index->search($data['query'], $data['version'] ?? null, $data['limit'] ?? 5);

        // structuredContent + JSON text fallback (src/Response.php::structured)
        return Response::structured(['results' => $results]);
    }
}
```

### Response API (verified in `src/Response.php`, `src/ResponseFactory.php`)

```php
Response::text(string $text);
Response::json(mixed $content);
Response::structured(array $data): ResponseFactory;         // structuredContent + text fallback
Response::make(Response|array $responses): ResponseFactory; // ->withStructuredContent([...])->withMeta([...])
Response::error(string $text);                               // isError: true
Response::image(string $data, string $mimeType = 'image/png');
Response::audio(string $data, string $mimeType = 'audio/wav');
Response::blob(string $content);
Response::fromStorage(string $path, ?string $disk = null, ?string $mimeType = null);
Response::resourceLink(...);                                 // link to a resource URI
Response::html(string $path); Response::view(string $view, array $data = []); // for MCP Apps (ui:// resources)
Response::notification(string $method, array $params = []); // yield from a generator for progress
$response->withMeta(array|string $meta, mixed $value = null);
```

Returning an array of `Response` gives multiple content blocks; returning a `Generator` streams (SSE on HTTP).

### Resources, resource templates, prompts

```php
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;
use Laravel\Mcp\Server\Attributes\{Description, MimeType};

#[Description('A documentation page as Markdown.')]
#[MimeType('text/markdown')]
class DocPage extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('docs://{version}/{slug}'); // must be scheme://...{var}...
    }

    public function handle(Request $request): Response
    {
        return Response::text(app(DocsRepository::class)->markdown($request->get('version'), $request->get('slug')));
    }
}
```

Static resources set `protected string $uri = 'docs://index';` (default scheme `file`, `src/Server/Resource.php`). Prompts extend `Laravel\Mcp\Server\Prompt`, define `arguments(): array` of `new Laravel\Mcp\Server\Prompts\Argument(name:, description:, required:)`, and return `Response::text(...)` (optionally `->asAssistant()`).

### Auth (web servers)

- Any route middleware: `Mcp::web(...)->middleware('auth:sanctum')` or custom token middleware; `$request->user()` available in tools (`Laravel\Mcp\Request::user()`).
- OAuth 2.1 (recommended by docs): `Mcp::oauthRoutes()` + `->middleware('auth:api')` with Laravel Passport; registers `/.well-known/oauth-protected-resource[/{path}]`, `/.well-known/oauth-authorization-server[/{path}]`, `POST oauth/register` (dynamic client registration) and a single `mcp:use` scope. Publish consent view with `--tag=mcp-views` and call `Passport::authorizationView(fn ($p) => view('mcp.authorize', $p))`. Real-world: nashra-app `bootstrap/app.php:77-87`.
- For public docs, no auth is needed; just throttle.

### Testing

```php
DocsServer::tool(SearchDocs::class, ['query' => 'install'])->assertOk()->assertSee('Installation');
DocsServer::actingAs($user)->resource(DocPage::class, ['version' => '3.x', 'slug' => 'install']);
DocsServer::prompt(WriteDocsPage::class, [...]);
```
(`Server::__callStatic` => `Laravel\Mcp\Server\Testing\PendingTestResponse`.)

### New in 1.0 worth using

- **MCP Apps**: `AppResource` (`ui://` scheme, `text/html;profile=mcp-app`), `#[RendersApp(resource: X::class, visibility: [Visibility::App|Model])]` on a tool, `#[AppMeta(connectDomains:, permissions:, libraries:)]`. Server auto-advertises extension `io.modelcontextprotocol/ui`. Could render an interactive docs page inside Claude/ChatGPT hosts. Source: `src/Server/AppResource.php`, https://laravel.com/framework/docs/13.x/mcp#apps
- **ToolSearch**: `ToolSearch::class => [ ...tools ]` in `$tools` exposes `search_tools` + `execute_tools` only.
- **MCP client**: `Laravel\Mcp\Client::web($url)` / `Client::local('php', ['artisan','mcp:start','x'])`, `->tools()`, `->callTool()`, `->readResource()`. laravel/ai agents can spread `...Mcp::client('x')->tools()` into `tools()`.
- **Cache hints** via `#[Cacheable]` and `cacheHints()`.

---

## 2. laravel/ai (Laravel AI SDK)

### Versions and requirements (verified)

| Item | Value | Source |
| --- | --- | --- |
| Latest | `v1.2.3` (2026-10-15); nashra-app has `v1.0.1` | Packagist p2 JSON; `installed.json` |
| PHP | `^8.3` | `vendor/laravel/ai/composer.json`, Packagist |
| Laravel | `illuminate/* ^12.0\|^13.0` and `illuminate/json-schema ^12.62\|^13.15` => **Laravel 12.62+ or 13.15+** | same |
| Other deps | `laravel/prompts ^0.3.6`, `laravel/serializable-closure ^2.0`, (1.2.x adds `symfony/yaml ^7.2\|^8.0`) | Packagist |
| Provider | `Laravel\Ai\AiServiceProvider` (auto-discovered); global helpers in `Laravel\Ai` namespace: `agent()`, `pipeline()` (`functions.php`) | |
| Setup | `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` then `php artisan migrate` (creates `agent_conversations`, `agent_conversation_messages`) | https://laravel.com/docs/12.x/ai-sdk#installation |
| Docs | https://laravel.com/framework/docs/13.x/ai-sdk | |

Note: Laravel 13 (latest `v13.37.0`, 2026-10-16) itself requires PHP `^8.3`.

### Provider config (`config/ai.php`)

Keys: `default` (`openai`), `default_for_images` (`gemini`), `default_for_audio`, `default_for_transcription`, `default_for_embeddings` (`openai`), `default_for_reranking` (`cohere`), `default_for_classification`, `caching.embeddings.{cache,store,individually}`, `providers.{name}.{driver,key,url,...}`. Drivers: `anthropic, azure, bedrock, cohere, deepseek, eleven, gemini, groq, jina, mistral, ollama, openai, openai-compatible, openrouter, typesafe, voyageai, xai` (`vendor/laravel/ai/config/ai.php`, enum `Laravel\Ai\Enums\Lab`). Env: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`, etc. Failover: pass an array of providers (`prompt(..., provider: [Lab::Anthropic, Lab::OpenAI])`) or `#[Provider([...])]`.

### Agent contracts and attributes

Contracts (`src/Contracts`): `Agent` (requires `instructions()`), `Conversational` (`messages(): iterable`), `HasTools` (`tools(): iterable` of `Tool|Agent|ProviderTool`), `HasStructuredOutput` (`schema(JsonSchema $schema): array`), `HasMiddleware`, `HasProviderOptions`, `RemembersConversations`. Trait `Laravel\Ai\Promptable` provides:

```php
prompt(AgentInput|UserMessage|Decisions|string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
stream(AgentInput|UserMessage|Decisions|string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): StreamableAgentResponse
queue(...): QueuedAgentResponse
broadcast(..., Channel|array $channels, ...) / broadcastNow(...) / broadcastOnQueue(...)
withMessages(iterable $messages): static
withTools(Closure|iterable $tools): static
```

Attributes (`src/Attributes`): `#[Provider(Lab|array|string)]`, `#[Model('...')]`, `#[MaxSteps(n)]`, `#[MaxTokens(n)]`, `#[Temperature(f)]`, `#[TopP(f)]`, `#[Timeout(s)]`, `#[UseCheapestModel]`, `#[UseSmartestModel]`, `#[CacheInstructions('1h')]`, `#[CacheToolDefinitions('1h')]` (Anthropic/Bedrock prompt caching), `#[Strict]`, `#[RepairToolCalls]`, `#[WithoutBroadcasting]`. Commands: `make:agent` (`--structured`), `make:tool`, `make:agent-middleware`, and a chat command (`src/Console/Commands/ChatCommand.php`).

### Minimal docs-assistant agent + tool

```php
namespace LaRecipe\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\{MaxSteps, Provider, Timeout, UseCheapestModel};
use Laravel\Ai\Contracts\{Agent, Conversational, HasTools};
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(['anthropic', 'openai'])]   // failover order (provider keys from config/ai.php)
#[UseCheapestModel]
#[MaxSteps(6)]
#[Timeout(60)]
class DocsAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(private string $version, private array $history = []) {}

    public function instructions(): Stringable|string
    {
        return "Answer only from the {$this->version} documentation. Cite page URLs. Say so if the docs do not cover it.";
    }

    public function messages(): iterable
    {
        return $this->history;
    }

    public function tools(): iterable
    {
        return [
            // A laravel/mcp Tool instance works directly: GeneratesText wraps it in
            // Laravel\Ai\Tools\McpServerTool (src/Providers/Concerns/GeneratesText.php:184).
            app(\LaRecipe\Mcp\Tools\SearchDocs::class),
            app(\LaRecipe\Mcp\Tools\ReadPage::class),
        ];
    }
}
```

Native tool contract (`Laravel\Ai\Contracts\Tool`, stub `vendor/laravel/ai/stubs/tool.stub`):

```php
class ReadPage implements \Laravel\Ai\Contracts\Tool
{
    public function description(): Stringable|string { return 'Read a docs page as Markdown by slug.'; }
    public function handle(\Laravel\Ai\Tools\Request $request): Stringable|string { return Docs::markdown($request['slug']); }
    public function schema(JsonSchema $schema): array { return ['slug' => $schema->string()->required()]; }
}
```

**Key design fact:** write each docs tool once as a `Laravel\Mcp\Server\Tool`; reuse it in the MCP server AND in a laravel/ai agent (in-site "Ask the docs" chat). Verified: `McpServerTool::supports($tool)` is `$tool instanceof \Laravel\Mcp\Server\Tool` (`vendor/laravel/ai/src/Tools/McpServerTool.php`).

### Structured output

```php
class Summarize implements Agent, HasStructuredOutput {
    use Promptable;
    public function instructions(): string { return 'Summarize the page.'; }
    public function schema(JsonSchema $schema): array {
        return ['summary' => $schema->string()->required(), 'keywords' => $schema->array()->items($schema->string())->required()];
    }
}
$res = (new Summarize)->prompt($markdown);  $res['summary'];
// ad hoc:
use function Laravel\Ai\agent;
agent(instructions: '...', schema: fn (JsonSchema $s) => ['n' => $s->integer()->required()])->prompt('...');
```

### Streaming to a frontend

`StreamableAgentResponse` (`src/Responses/StreamableAgentResponse.php`) implements `Responsable` and `IteratorAggregate`:

- Default (`toResponse`): SSE, each event as `data: {json}\n\n`, terminated by `data: [DONE]`, headers `Content-Type: text/event-stream`, `Cache-Control: no-cache, no-transform`, `X-Accel-Buffering: no`.
- `->usingVercelDataProtocol(?string $messageId = null)`: Vercel AI SDK **UI message stream** protocol (parts `start`, `text-start`, `text-delta`, `text-end`, `reasoning-*`, `tool-input-available`, `tool-output-available`, `tool-approval-request`, `source-url`, `start-step`/`finish-step`, `finish`, `error`), header `x-vercel-ai-ui-message-stream: v1` (`src/Streaming/Protocols/VercelDataProtocol.php`). Works with `useChat` from `@ai-sdk/react` (AI SDK 5+ UI message stream; exact AI SDK major compatibility unverified).
- `->usingAgentUserInteractionProtocol(?string $threadId, ?string $runId)`: AG-UI protocol (`src/Streaming/Protocols/AgentUserInteractionProtocol.php`).
- `->usingProtocol(StreamProtocol $p)`: custom.
- `->then(fn (StreamedAgentResponse $r) => ...)` after completion (`$r->text`, `$r->events`, `$r->usage`, `$r->meta->citations`); `->each(fn (StreamEvent $e) => ...)`.
- Event classes: `src/Streaming/Events/{StreamStart,TextStart,TextDelta,TextEnd,ReasoningStart,ReasoningDelta,ReasoningEnd,ToolCall,ToolResult,ToolApprovalRequest,Citation,ProviderToolEvent,Error,StreamEnd}.php`.

Minimal controller for `useChat`:

```php
use Illuminate\Http\Request;
use Laravel\Ai\Vercel\Vercel;

class AskDocsController
{
    public function __invoke(Request $request, string $version)
    {
        $chat = Vercel::chat($request);                    // reads `messages` (UI messages) from body

        return (new DocsAssistant($version))
            ->withMessages($chat->history())               // earlier turns
            ->stream($chat)                                // newest user msg or tool-approval decisions
            ->usingProtocol($chat->protocol());            // VercelDataProtocol
    }
}
// Route::post('docs/{version}/ask', AskDocsController::class)->middleware('throttle:10,1');
```

```tsx
// React (AI SDK UI). Package names per ai-sdk.dev; versions unverified here.
import { useChat } from '@ai-sdk/react';
import { DefaultChatTransport } from 'ai';
const { messages, sendMessage, status } = useChat({
  transport: new DefaultChatTransport({ api: `/docs/${version}/ask`, headers: { 'X-CSRF-TOKEN': csrf } }),
});
```

`Vercel::toUiMessages($conversation->messages)` rehydrates stored history into `useChat`. AG-UI equivalent: `Laravel\Ai\AgentUserInteraction\AgentUserInteraction::chat($request)`, `::toClientState($messages)`.

Conversation memory: implement `Laravel\Ai\Contracts\RemembersConversations` and use trait `Laravel\Ai\Concerns\RemembersConversations`, then `(new DocsAssistant)->forUser($user)->stream(...)` or `->continue($conversationId, as: $user)` (`src/Contracts/RemembersConversations.php`). Stored in the `agent_conversations` / `agent_conversation_messages` tables, so this needs the host to run the AI SDK migrations; for anonymous docs visitors, keep history client-side (`useChat` sends full `messages`) instead.

### Embeddings, vector search, vector stores, reranking

```php
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

$vec  = Str::of('How do I install?')->toEmbeddings();                 // Stringable macro (AiServiceProvider:55)
$resp = Embeddings::for($chunks)->dimensions(1536)->cache()->generate(Lab::OpenAI, 'text-embedding-3-small');
$resp->embeddings; // float[][]

// Schema + query (framework, Postgres + pgvector ONLY: Builder::ensureConnectionSupportsVectors throws otherwise)
Schema::ensureVectorExtensionExists();
$table->vector('embedding', dimensions: 1536)->index();   // HNSW cosine
DocChunk::query()->whereVectorSimilarTo('embedding', 'install on Herd', minSimilarity: 0.4)->limit(10)->get();
// also: selectVectorDistance(), whereVectorDistanceLessThan(), orderByVectorDistance()

// As an agent tool:
\Laravel\Ai\Tools\SimilaritySearch::usingModel(DocChunk::class, 'embedding', minSimilarity: 0.6, limit: 15)
    ->rerank('content', limit: 5);

// Hosted vector stores (provider-side RAG): OpenAI, Azure, Gemini, xAI
$store = \Laravel\Ai\Stores::create('larecipe-3x');       // ->add(...), ->remove(...), Stores::get($id)
new \Laravel\Ai\Providers\Tools\FileSearch(stores: [$store->id]);

// Reranking: Laravel\Ai\Reranking::of($docs)->...; Collection macro ->rerank($by, $query)
```

Implication for LaRecipe: semantic search via pgvector is Postgres-only; SQLite/MySQL users need a fallback (keyword/BM25 search, or a provider vector store via `Stores` + `FileSearch`). Provider tools also exist: `WebSearch`, `WebFetch`, `FileSearch`, `CodeExecution`, `ToolSearch` (`src/Providers/Tools`).

### Testing

`DocsAssistant::fake([...])`, `Embeddings::fake()`, `Stores::fake()`, `Reranking::fake()` plus `assert*` helpers (`src/Concerns/InteractsWithFake*.php`).

---

## 3. laravel/boost (shipping guidelines and skills from a package)

### Versions (verified)

Latest `v2.10.3` (2026-10-07), nashra-app has `v2.10.1`; PHP `^8.2`; depends on `laravel/mcp ^0.7.1|^0.8|^0.9|^1.0`, `laravel/roster ^1.0`. Installed as `--dev`. Docs: https://laravel.com/docs/12.x/boost (13.x mirror at https://laravel.com/framework/docs/13.x/boost).

### File layout inside a third-party package (verified in source)

`Laravel\Boost\Support\PackageRegistry::boostPath()` resolves `{package_root}/resources/boost/{guidelines|skills}` and requires the directory to exist (`vendor/laravel/boost/src/Support/PackageRegistry.php`).

```
larecipe/                          (composer package root)
  resources/boost/
    guidelines/
      core.blade.php               # or core.md; ANY *.md / *.blade.php file in this tree is picked up
      writing-docs.md              # extra files become separate keys
    skills/
      larecipe-docs-authoring/
        SKILL.md                   # or SKILL.blade.php; frontmatter `name` + `description` REQUIRED
        references/components.md   # whole directory is copied
      larecipe-docs-sync/
        SKILL.md
```

Discovery rules (`src/Install/ThirdPartyPackage.php`, `src/Install/GuidelineComposer.php`, `src/Install/SkillComposer.php`):

- Only **direct** dependencies (`$package->isDirect()`), Composer AND npm, that are NOT first-party. First-party = any `laravel/*` plus `livewire/livewire`, `livewire/flux`, `livewire/flux-pro`, `livewire/volt`, `inertiajs/inertia-laravel`, `pestphp/pest`, `phpunit/phpunit` (`src/Support/Composer.php`). So `binarytorch/larecipe` (or whatever vendor) is third-party: good. If LaRecipe is installed as `require-dev` it is still direct.
- Guideline keys are `{composer/name}/{relative path without extension}`, e.g. `binarytorch/larecipe/core`. Guidelines are concatenated as `=== {key} rules ===` sections inside a `<laravel-boost-guidelines>...</laravel-boost-guidelines>` block in the agent's guideline file; user content outside the block is preserved (`src/Install/GuidelineWriter.php`).
- Users can override a third-party guideline by creating `.ai/guidelines/{same key}.md|.blade.php` (`GuidelineComposer::guidelinePath`), exclude via `config('boost.guidelines.exclude')`, exclude skills via `config('boost.skills.exclude')`.
- `boost:install` shows a multiselect "Which third-party AI guidelines/skills would you like to install?" and records choices in `boost.json` under `packages` (nashra-app `boost.json`: `"packages": ["laravel/ai", "spatie/laravel-medialibrary"]`). `boost:update` re-syncs and, by default (`--discover`), prompts for newly available packages. Docs suggest `"post-update-cmd": ["@php artisan boost:update --ansi"]`.
- Blade guidelines are rendered with `$assist` (`Laravel\Boost\Install\GuidelineAssist`) available; backticks, `<?php`, `@include`, `<x-` are escaped before rendering. `@boostsnippet('name', 'php') ... @endboostsnippet` emits a fenced example. Docs example uses `@verbatim <code-snippet name="..." lang="php"> ... </code-snippet> @endverbatim`.
- Path-scoped blocks: `@scoped(['resources/docs/**']) ... @endscoped`. Inline by default; when `boost.rules.scoped_guidelines` is true they are extracted to `.ai/rules/boost/*.md` with `paths:` frontmatter (`src/Concerns/RendersBladeGuidelines.php`, `src/Install/RuleComposer.php`). Ideal for "docs-writing rules only when touching docs files".
- A failing Blade render records a `RenderFailures` entry and the guideline is skipped.

Real example: `vendor/spatie/laravel-medialibrary/resources/boost/guidelines/core.blade.php` (short, points agents to the skill) and `.../skills/medialibrary-development/SKILL.md` with frontmatter `name`, `description`, `license: MIT`, `metadata.author`, plus `references/medialibrary-guide.md`.

Suggested LaRecipe guideline (`resources/boost/guidelines/core.blade.php`):

```blade
## LaRecipe Docs

- Docs live in `{{ config('larecipe.docs.path', 'resources/docs') }}/{version}/*.md`; navigation in `index.md`.
- After changing public behavior (routes, config, commands, public classes), update the matching docs page in the same change.
- Activate the `larecipe-docs-authoring` skill when creating or editing docs pages or components.
- Use the `larecipe` MCP server (`search_docs`, `read_page`) to read existing docs before writing new ones.

@scoped(['resources/docs/**'])
- Frontmatter `title` and `description` are required. Use `:::note` / `:::warning` blocks, not raw HTML.
@endscoped
```

(Whether `config()` resolves inside Boost's Blade render: Boost renders inside the booted app via `Blade::render`, so `config()` should work; unverified in practice.)

### Which agents and files Boost writes (v2.10.x, verified per class in `src/Install/Agents/*.php`)

| Agent (`boost.json` key) | Guidelines file | Skills dir | MCP config file (key) |
| --- | --- | --- | --- |
| Claude Code (`claude_code`) | `CLAUDE.md` if it exists, else `AGENTS.md` | `.claude/skills` | `.mcp.json` (`mcpServers`) |
| Codex (`codex`) | `AGENTS.md` | `.agents/skills` | `.codex/config.toml` (`mcp_servers`) |
| GitHub Copilot (`copilot`) | `AGENTS.md` | `.github/skills` | `.vscode/mcp.json` (`servers`) |
| Cursor (`cursor`) | `AGENTS.md` | `.cursor/skills` | `.cursor/mcp.json` |
| Junie (`junie`) | `AGENTS.md` | `.junie/skills` | `.junie/mcp/mcp.json` |
| Amp (`amp`) | `AGENTS.md` | `.agents/skills` | `.amp/settings.json` (`amp.mcpServers`) |
| Antigravity (`antigravity`, detects `.gemini`) | `AGENTS.md` | `.agents/skills` | `.agents/mcp_config.json` |
| OpenCode (`opencode`) | `AGENTS.md` | `.agents/skills` | `opencode.json[c]` (`mcp`) |
| Zed (`zed`) | `AGENTS.md` | `.agents/skills` | `.zed/settings.json` (`context_servers`) |
| Kiro (`kiro`) | `AGENTS.md` | `.kiro/skills` | `.kiro/settings/mcp.json` |
| Factory Droid (`factory`) | `AGENTS.md` | `.factory/skills` | `.factory/mcp.json` |
| Grok Build (`grok_build`) | `AGENTS.md` | `.grok/skills` | `.grok/config.toml` (`mcp_servers`) |
| Pi (`pi`) | `AGENTS.md` | `.pi/skills` | none |

Every path is overridable via `config('boost.agents.{key}.guidelines_path|skills_path|mcp_config_path')`. Note: no `.github/copilot-instructions.md` and no `.cursor/rules/*.mdc` in 2.10 (Copilot and Cursor both read `AGENTS.md` now). Gemini CLI is listed in the docs' setup tabs, but there is no dedicated `GeminiCli` agent class in 2.10.1 (unverified whether Antigravity covers it). Custom agents: `Boost::registerAgent('name', CustomAgent::class)` with contracts `SupportsGuidelines`, `SupportsSkills`, `SupportsMcp`.

Skill writing (`src/Install/SkillWriter.php`): third-party skills are **copied** into each agent's skills dir (and to `.ai/skills/{name}` canonical copy if it exists); user skills in `.ai/skills/{name}` are symlinked (or copied if they contain Blade). Skill names must pass `SkillWriter::isValidSkillName`. Stale skills are removed on `boost:update`. Other commands: `boost:add-skill {owner/repo} [--skill=*] [--all]` (remote GitHub skills with a security audit), `boost:list-skills`, `boost:mcp` (Boost's own stdio server).

### Boost MCP server facts relevant to a docs package

- Boost's `search-docs` tool POSTs `{queries, packages:[{name, version:"{major}.x"}], token_limit, format:"markdown"}` to `config('boost.hosted.api_url', 'https://boost.laravel.com').'/api/docs'` (`src/Mcp/Tools/SearchDocs.php`). It only covers packages Laravel indexes (Laravel, Filament, Flux, Inertia, Livewire, Nova, Pest, Tailwind), not arbitrary packages. A docs package fills that gap for project-specific docs.
- Users can inject extra tools into Boost's server: `config('boost.mcp.tools.include', [FQCN...])` (`src/Mcp/ToolRegistry.php`, `src/Mcp/Boost.php::filterPrimitives`); same for `boost.mcp.resources.include` / `boost.mcp.prompts.include`. Boost runs tools in a subprocess (`boost:execute-tool`). A package could `config()->push('boost.mcp.tools.include', SearchDocs::class)` in its provider so docs tools appear inside `laravel-boost` without a second MCP server (works in principle; unverified side effects since tool must be a `Laravel\Mcp\Server\Tool` constructible by the container).

---

## 4. Claude Code extensibility

Verified on 2026-10-17 against the raw Markdown versions of the official docs (every page at `https://code.claude.com/docs/en/<page>` is also served as `<page>.md`). Version numbers such as "v2.1.218+" are quoted from the docs.

### 4.1 Agent Skills (`SKILL.md`)

Source: https://code.claude.com/docs/en/skills (the old `/slash-commands` URL now serves the same page).

- Claude Code skills "follow the Agent Skills (https://agentskills.io) open standard". Claude Code adds its own extensions on top.
- **Custom commands have been merged into skills.** `.claude/commands/deploy.md` and `.claude/skills/deploy/SKILL.md` both create `/deploy`. Legacy command files still work, but skills add a supporting-files directory, invocation control, and auto-loading.

**Where skills live**

| Scope | Path | Notes |
|---|---|---|
| Enterprise | `.claude/skills/<name>/SKILL.md` in the managed settings dir | highest priority |
| Personal | `~/.claude/skills/<name>/SKILL.md` | all projects |
| Project | `.claude/skills/<name>/SKILL.md` at repo root | commit to VCS |
| Nested (monorepo) | `<subdir>/.claude/skills/<name>/SKILL.md` | loads when working under `<subdir>`; a name clash becomes `/apps/web:deploy` |
| `--add-dir` | `.claude/skills/...` in the added dir | live-watched |
| Plugin | `<plugin>/skills/<name>/SKILL.md` | namespaced `/plugin-name:skill-name` |
| claude.ai account | synced to `~/.claude/skills/synced/` | `anthropic-skills:` prefix is reserved |

**Directory layout**

```text
.claude/skills/larecipe-docs/
├── SKILL.md          # required: frontmatter + instructions (keep under 500 lines)
├── reference.md      # loaded on demand when SKILL.md links to it
├── examples.md
└── scripts/
    └── affected.sh   # executed, not loaded as text
```

**Frontmatter fields (Claude Code).** All are optional. `description` is recommended.

| Field | Meaning |
|---|---|
| `name` | Command name. Defaults to the directory name |
| `description` | What the skill does and when to use it. Combined with `when_to_use`, it is truncated at 1,536 chars in the listing |
| `when_to_use` | Extra trigger context, appended to the description |
| `argument-hint` | Autocomplete hint, e.g. `[page] [section]` |
| `arguments` | Named positional args for `$name` substitution |
| `disable-model-invocation` | `true`: only the user can run `/name`. The description is hidden from Claude |
| `user-invocable` | `false`: hidden from the `/` menu. Only Claude can invoke it |
| `allowed-tools` | Tools pre-approved for the invoking turn, e.g. `Read Grep Bash(git diff *)` |
| `disallowed-tools` | Tools removed while the skill is active |
| `model`, `effort` | Per-turn model and effort override (`low` / `medium` / `high` / `xhigh` / `max`) |
| `context: fork` + `agent` | Run the skill in an isolated subagent (`Explore`, `Plan`, `general-purpose`, or a custom agent) |
| `background` | With `context: fork`, `false` waits for the result (v2.1.218+) |
| `hooks` | Hooks registered when the skill is invoked |
| `paths` | Globs. The skill auto-activates only when Claude works on matching files |
| `shell` | `bash` or `powershell`, for `` !`cmd` `` injection |
| `metadata`, `license`, `compatibility` | Agent Skills spec fields. Claude Code accepts them and ignores them |

**Portability constraint (important for a package that ships skills to many agents).** The claude.ai uploads, the Skills API and `package_skill.py` accept only `name`, `description`, `license`, `compatibility`, `metadata`, `allowed-tools`. Any other key is a hard error: `Unexpected key(s) in SKILL.md frontmatter: argument-hint. Allowed properties are: allowed-tools, compatibility, description, license, metadata, name`. To support every agent, ship spec-only frontmatter and treat the Claude-only keys as optional extras.

**Substitutions and dynamic context**

- Argument substitutions: `$ARGUMENTS`, `$ARGUMENTS[N]`, `$N`, and `$name`.
- Path and session variables: `${CLAUDE_SESSION_ID}`, `${CLAUDE_EFFORT}`, `${CLAUDE_SKILL_DIR}`, `${CLAUDE_PROJECT_DIR}`, `${CLAUDE_PLUGIN_ROOT}`, `${CLAUDE_PLUGIN_DATA}`.
- `` !`command` `` (inline) or a fenced ```` ```! ```` block runs a shell command before the prompt is sent, and its output is inlined. Commands go through the normal permission rules. A non-zero exit aborts the skill, except exit 1 from grep, diff and similar tools.

Example of a portable skill a docs package could install:

```markdown
---
name: larecipe-docs
description: Find, read and update this project's LaRecipe documentation. Use when code changes affect documented behavior, when asked about the docs, or when editing files under resources/docs.
allowed-tools: Read Grep Glob Bash(php artisan larecipe:*)
---
# LaRecipe docs
Docs live in `resources/docs/{version}/*.md`. Before finishing a code change, run
`php artisan larecipe:affected --format=json` and update every page it lists.
See [conventions.md](conventions.md) for frontmatter and component syntax.
```

**Lifecycle**

- The rendered skill enters context once and stays there.
- After compaction, the most recent invocation of each skill is re-attached (first 5,000 tokens).
- `.claude/skills/` is live-watched. `/reload-skills` picks up a top-level directory created mid-session.

**Related: path-scoped rules** (https://code.claude.com/docs/en/memory). `.claude/rules/*.md` files can carry `paths:` frontmatter. A rule loads only when Claude reads or edits a matching file. This fits docs-writing conventions:

```markdown
---
paths:
  - "resources/docs/**/*.md"
---
# LaRecipe writing rules
- Use `:::note` / `:::warning` directives, not raw HTML.
```

**AGENTS.md interplay** (memory docs; requires v2.1.277+):

- Claude Code reads `AGENTS.md` only when there is no `CLAUDE.md`, `.claude/CLAUDE.md` or `CLAUDE.local.md` in the working directory or above it.
- Otherwise `CLAUDE.md` should import it with `@AGENTS.md`.
- Claude Code does not read `AGENTS.local.md`, `AGENTS.override.md`, or `.agents/`.

### 4.2 Hooks

Source: https://code.claude.com/docs/en/hooks

**Event names (all 33 current events)**

- Per session: `SessionStart`, `Setup`, `SessionEnd`.
- Per turn: `UserPromptSubmit`, `UserPromptExpansion`, `Stop`, `StopFailure`.
- Inside the agentic loop: `PreToolUse`, `PermissionRequest`, `PermissionDenied`, `PostToolUse`, `PostToolUseFailure`, `PostToolBatch`.
- Subagents, tasks and teams: `SubagentStart`, `SubagentStop`, `TaskCreated`, `TaskCompleted`, `TeammateIdle`.
- Notifications and display: `Notification`, `MessageDisplay`.
- Context and config: `InstructionsLoaded`, `ConfigChange`, `CwdChanged`, `DirectoryAdded`, `FileChanged`.
- Worktrees: `WorktreeCreate`, `WorktreeRemove`.
- Compaction: `PreCompact`, `PostCompact`.
- Model switching: `PreModelSwitch`, `PostModelSwitch`.
- MCP elicitation: `Elicitation`, `ElicitationResult`.

**Where hooks are defined**

- `~/.claude/settings.json`, `.claude/settings.json` (committed), `.claude/settings.local.json`, and managed policy.
- A plugin's `hooks/hooks.json`.
- Skill frontmatter (active for the rest of the session) and subagent frontmatter (active while the subagent runs).
- Entries merge across levels.
- Project hooks run only after the user accepts the workspace-trust dialog. Under `claude -p`, project hooks DO run without a trust prompt unless `--bare` is passed.

**settings.json shape**

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "php \"$CLAUDE_PROJECT_DIR\"/artisan larecipe:hook post-tool-use",
            "timeout": 30
          }
        ]
      }
    ],
    "Stop": [
      { "hooks": [ { "type": "command", "command": "php \"$CLAUDE_PROJECT_DIR\"/artisan larecipe:hook stop" } ] }
    ],
    "SessionStart": [
      { "matcher": "startup|resume|compact", "hooks": [ { "type": "command", "command": "php \"$CLAUDE_PROJECT_DIR\"/artisan larecipe:hook session-start" } ] }
    ]
  }
}
```

**Handler types**

- `command`: has `command`, an optional `args` (exec form, no shell), `async`, `asyncRewake`, `shell`, and `onFailure: "continue" | "block"`.
- `http`: has `url`, `headers`, `allowedEnvVars`. The input arrives as a POST body.
- `mcp_tool`: has `server`, `tool`, `input`.
- `prompt` and `agent`: an LLM verifier that returns `{"ok": bool, "reason": "...", "impossible": bool}`. Agent hooks are marked experimental.
- Common fields: `if` (permission-rule filter such as `Bash(git *)`), `timeout`, `statusMessage`, `once`.
- `SessionStart` and `Setup` support only `command` and `mcp_tool`.

**Matcher**

- An exact name or `A|B`. If the string contains other special characters, it is treated as a regex.
- It matches against the tool name for tool events, the source for `SessionStart` (`startup`, `resume`, `clear`, `compact`, `fork`), and the trigger for `PreCompact` (`manual`, `auto`).
- `Stop`, `UserPromptSubmit`, `PostToolBatch`, and a few other events take no matcher.

**Input on stdin (JSON)**

- Common fields: `session_id`, `prompt_id`, `transcript_path`, `cwd`, `scratchpad_dir`, `permission_mode`, `effort`, `hook_event_name`, plus `agent_id` and `agent_type` inside subagents.
- `Stop` adds `stop_hook_active`, `last_assistant_message`, `background_tasks`, `session_crons`.
- Environment variables: `CLAUDE_PROJECT_DIR`, `CLAUDE_PLUGIN_ROOT`, `CLAUDE_PLUGIN_DATA`, `CLAUDE_EFFORT`, `CLAUDE_CODE_REMOTE`.

**Exit codes**

- `0`: success. Stdout is parsed as JSON if it is a single JSON object.
- `2`: blocking error. Stderr goes to Claude and the action is blocked. This works on events that can block: `PreToolUse`, `UserPromptSubmit`, `Stop`, `SubagentStop`, `PreCompact`, `PostToolBatch`, `TaskCreated`, `TaskCompleted`, `ConfigChange`, `PreModelSwitch`, `Elicitation`, and others.
- Other codes: non-blocking error, unless the hook sets `onFailure: "block"`.
- `PostToolUse` and `SessionStart` cannot block.

**JSON output**

- Universal fields: `continue` (false stops Claude entirely), `stopReason`, `systemMessage` (shown to the user), `terminalSequence`. `suppressOutput` is accepted but has no effect.
- Top-level `decision: "block"` plus `reason`: used by `UserPromptSubmit`, `PostToolUse`, `PostToolBatch`, `Stop`, `SubagentStop`, `ConfigChange`, `PreCompact`, `TaskCreated`.
- `hookSpecificOutput` (must include `hookEventName`):
  - `PreToolUse`: `permissionDecision: allow|deny|ask|defer`, `permissionDecisionReason`, `updatedInput`, `additionalContext`.
  - `PostToolUse`: `updatedToolOutput`, `additionalContext`.
  - `SessionStart`: `additionalContext`, `initialUserMessage`, `sessionTitle`, `watchPaths`, `reloadSkills`.
  - `UserPromptSubmit`: `additionalContext`. Plain stdout is also injected as context for this event.
  - `PermissionRequest`: `decision.behavior: allow|deny`.
- Size cap: `additionalContext`, `systemMessage` and plain stdout are capped at 10,000 characters each. Anything longer is saved to a file and replaced with a 2,000-char preview.

**Making Claude keep working from a Stop hook** (key for docs-drift nudges):

```json
{ "decision": "block", "reason": "Code in app/Billing changed; update resources/docs/1.x/billing.md" }
```

A softer non-error variant is shown in the transcript as "Stop hook feedback" rather than a hook error:

```json
{ "hookSpecificOutput": { "hookEventName": "Stop", "additionalContext": "Docs pages billing.md, webhooks.md reference files you changed. Update them or explain why not." } }
```

- Loop protection: check `stop_hook_active` (true when Claude is already continuing because of a Stop hook).
- An 8-consecutive-continuation cap applies; `CLAUDE_CODE_STOP_HOOK_BLOCK_CAP` raises it.
- `/goal` is the built-in shortcut for a session-scoped prompt-based Stop hook.

**Codex parity** (https://learn.chatgpt.com/docs/hooks, redirected from developers.openai.com/codex/hooks). Codex has a very similar hook system:

- Config: `~/.codex/hooks.json`, `<repo>/.codex/hooks.json`, inline `[hooks]` in `config.toml`, or a plugin's `hooks/hooks.json`.
- Events: `PreToolUse`, `PermissionRequest`, `PostToolUse`, `PreCompact`, `PostCompact`, `UserPromptSubmit`, `Stop`, `SubagentStart`, `SubagentStop`, `SessionStart`, `SessionEnd`.
- `Stop` honors `decision: "block"` or exit 2.
- `additionalContext` is supported, with a default limit of about 2,500 tokens (`additionalContextLimit`).

One `php artisan larecipe:hook <event>` entry point can therefore serve both Claude Code and Codex. Codex payload field-level details are unverified beyond the doc summary.

### 4.3 Plugins and marketplaces

Sources:

- https://code.claude.com/docs/en/plugins-reference
- https://code.claude.com/docs/en/plugin-marketplaces
- https://code.claude.com/docs/en/plugins/marketplace-reference

**Plugin layout** (the manifest is optional; components outside it are auto-discovered):

```text
larecipe-claude/
├── .claude-plugin/plugin.json
├── skills/<name>/SKILL.md      # invoked as /larecipe:<name>
├── commands/*.md               # legacy, prefer skills/
├── agents/*.md                 # subagents (plugin agents ignore hooks/mcpServers/permissionMode)
├── hooks/hooks.json            # same shape as settings.json "hooks" (+ optional "description")
├── .mcp.json                   # MCP servers
├── .lsp.json, output-styles/, workflows/, themes/, monitors/monitors.json
├── bin/                        # on Bash PATH while enabled (blocks claude.ai/Cowork install)
└── settings.json               # only "agent" and "subagentStatusLine" apply
```

A `CLAUDE.md` at the plugin root is NOT loaded. To add instructions to Claude's context, put them in a skill.

**`plugin.json`**

- `name` is the only required field (kebab-case).
- Optional fields: `$schema`, `displayName`, `version`, `description`, `author{name,email,url}`, `homepage`, `repository`, `license`, `keywords`, `metadata`, `defaultEnabled`, `dependencies`, `settings`, `userConfig`, `channels`, `skills`, `commands`, `agents`, `hooks`, `mcpServers`, `lspServers`, `outputStyles`, `workflows`, `experimental{themes,monitors,evals}`.
- Unknown top-level keys are stripped, and `claude plugin validate [--strict]` reports them as warnings.

```json
{
  "name": "larecipe",
  "version": "3.0.0",
  "description": "LaRecipe docs skills, drift hooks and docs MCP server",
  "author": { "name": "LaRecipe" },
  "repository": "https://github.com/saleem-hadad/larecipe",
  "license": "MIT",
  "mcpServers": {
    "larecipe": { "command": "php", "args": ["${CLAUDE_PROJECT_DIR}/artisan", "larecipe:mcp"] }
  }
}
```

`${CLAUDE_PROJECT_DIR}` substitution in plugin MCP `command`, `args` and `env` is documented in https://code.claude.com/docs/en/mcp. Using it to launch `artisan` is my design inference: it should work but is unverified end to end.

**`marketplace.json`** (at `<repo>/.claude-plugin/marketplace.json`; `name`, `owner` and `plugins` are required):

```json
{
  "name": "larecipe",
  "owner": { "name": "LaRecipe" },
  "description": "Official LaRecipe Claude Code plugins",
  "plugins": [
    { "name": "larecipe", "source": "./claude-plugin", "description": "Docs skills, hooks, MCP" }
  ]
}
```

**Plugin entry sources**

- A relative path starting with `./`, resolved from the marketplace root. `..` is rejected.
- `{ "source": "github", "repo": "o/r" }`.
- `{ "source": "git-subdir", "url": "o/r", "path": "sub" }`.
- `url`, `archive` (zip over HTTPS), `npm`.
- `command`: prints an absolute plugin dir path; it runs from the user's HOME, not the project.
- Keep the entry `name` equal to the `plugin.json` name.

**How a repo publishes a plugin**

1. Commit `.claude-plugin/marketplace.json` to the repo, then validate with `claude plugin validate .`.
2. Users run `claude plugin marketplace add owner/repo`, then `claude plugin install larecipe@larecipe`. The in-session equivalent is `/plugin marketplace add ...`.
3. A project can pre-register the marketplace for every teammate in `.claude/settings.json`. This takes effect only after workspace trust:

```json
{
  "extraKnownMarketplaces": {
    "larecipe": { "source": { "source": "github", "repo": "saleem-hadad/larecipe" } }
  },
  "enabledPlugins": { "larecipe@larecipe": true }
}
```

**Marketplace source notes**

- `github` and `git` sources accept `ref`, `path` (defaults to `.claude-plugin/marketplace.json`) and `sparsePaths`. The Composer package repo itself can therefore double as the marketplace.
- A `directory` source needs an absolute path. You cannot portably point `extraKnownMarketplaces` at `vendor/...` from committed settings (absolute-path requirement per settings-reference; relative support unverified).
- Third-party marketplaces default to `autoUpdate: false`.

### 4.4 Subagents

Source: https://code.claude.com/docs/en/sub-agents

**Locations, by priority**

1. Managed settings.
2. The `--agents` JSON flag.
3. `.claude/agents/` (walked up from the cwd, scanned recursively).
4. `~/.claude/agents/`.
5. A plugin's `agents/`, namespaced `plugin:subdir:name`.

**File format:** Markdown with YAML frontmatter. The body is the system prompt. Required: `name` (no `:`, at most 256 chars) and `description`.

**Optional fields:** `tools`, `disallowedTools`, `model` (`sonnet`, `opus`, `haiku`, `fable`, a full ID, or `inherit`), `permissionMode`, `maxTurns`, `autoCompactWindow`, `skills` (preloads full skill content), `mcpServers`, `hooks`, `memory` (`user`, `project`, `local`), `background`, `omitClaudeMd`, `effort`, `isolation: worktree`, `color`, `initialPrompt`, `experimental.cacheTtl`.

Plugin subagents ignore `hooks`, `mcpServers` and `permissionMode`.

```markdown
---
name: docs-syncer
description: Updates LaRecipe docs pages after code changes. Use proactively when code under app/ changes.
tools: Read, Grep, Glob, Edit, Bash(php artisan larecipe:*)
model: sonnet
skills: [larecipe-docs]
---
You update documentation in resources/docs to match code changes...
```

### 4.5 claude-code-action (GitHub Action)

Sources:

- https://code.claude.com/docs/en/github-actions
- https://github.com/anthropics/claude-code-action (`action.yml`, `docs/*.md`, `src/github/context.ts`)
- Latest tag: `v1.0.252` (2026-10-16). Use `anthropics/claude-code-action@v1`.

**Modes** (`src/modes/detector.ts`)

- **Interactive / tag mode:** no `prompt` input. Responds to `trigger_phrase` (default `@claude`), `label_trigger` (default `claude`) or `assignee_trigger` in issues and PR comments, using a sticky tracking comment.
- **Automation / agent mode:** a `prompt` is set. Runs immediately, creates no tracking comment unless `track_progress: true`, and results go to the run log.

**Supported events (verified in source).** `src/github/context.ts` accepts:

- Entity events: `issues`, `issue_comment`, `pull_request`, `pull_request_target`, `pull_request_review`, `pull_request_review_comment`.
- Automation events: `workflow_dispatch`, `repository_dispatch`, `schedule`, `workflow_run`.

Any other event (including **`push`**) throws `Unsupported event type: push`. For "after merge to main" use one of:

- `pull_request: types: [closed]` with `if: github.event.pull_request.merged == true`
- `workflow_run` (after CI on main)
- `schedule`
- `workflow_dispatch`

**Inputs** (`action.yml`)

- Prompt and settings: `prompt`, `claude_args` (any CLI flags, e.g. `--max-turns`, `--model`, `--allowedTools`, `--mcp-config`, `--json-schema`), `settings` (JSON string or path).
- Auth: `anthropic_api_key` or `claude_code_oauth_token`. Workload identity federation: `anthropic_federation_rule_id`, `anthropic_organization_id`, `anthropic_service_account_id`, `anthropic_workspace_id`, `anthropic_oidc_audience`. Cloud providers: `use_bedrock`, `use_vertex`, `use_foundry`.
- GitHub token: `github_token` (when omitted, the action uses the Claude GitHub App).
- Triggers: `trigger_phrase`, `label_trigger`, `assignee_trigger`.
- Branches: `base_branch`, `branch_prefix` (default `claude/`), `branch_name_template`.
- Who may trigger: `allowed_bots` (default none, which prevents bot loops), `allowed_non_write_users`, `include_comments_by_actor`, `exclude_comments_by_actor`.
- Comments: `use_sticky_comment`, `classify_inline_comments`, `track_progress`, `include_fix_links`.
- Commits: `use_commit_signing`, `ssh_signing_key`, `bot_id`, `bot_name`.
- Plugins: `plugins` and `plugin_marketplaces` (newline-separated).
- Runtime: `additional_permissions`, `display_report`, `show_full_output`, `path_to_claude_code_executable`, `path_to_bun_executable`.

**Outputs:** `conclusion`, `execution_file`, `branch_name`, `github_token`, `structured_output` (with `--json-schema`), `session_id`.

**Skills in CI**

- `prompt: "/skill-name args"` works for repo skills once `actions/checkout` has run.
- For plugin skills, use `plugins: name@marketplace` plus `plugin_marketplaces: <git url>`, then `prompt: "/plugin:skill"`.
- For a plain-text prompt, Claude has no shell or GitHub API access until you grant tools with `--allowedTools` or through the `settings` permissions. A skill's own `allowed-tools` frontmatter also applies.

**PR creation behavior**

- The FAQ says: "Claude doesn't create PRs by default. Instead, it pushes commits to a branch and provides a link to a pre-filled PR submission page."
- On an open PR it pushes to the PR branch. On issues it creates a `claude/...` branch.
- Agent mode does not create branches itself; it works on what is checked out. An optional `CLAUDE_BRANCH` env var is used by the auto-fix examples.
- Pushes made with the default `GITHUB_TOKEN` do not trigger other workflows. Use the Claude App token (the default when `github_token` is omitted) or a custom App or PAT.
- The Claude App cannot modify workflow files.

**Pattern A: update docs on the PR branch** (official `docs/solutions.md`, "Documentation Sync on API Changes"):

```yaml
on:
  pull_request:
    types: [opened, synchronize]
    paths: ["app/**", "routes/**", "config/**"]
jobs:
  doc-sync:
    runs-on: ubuntu-latest
    permissions: { contents: write, pull-requests: write, id-token: write }
    steps:
      - uses: actions/checkout@v6
        with: { ref: "${{ github.event.pull_request.head.ref }}", fetch-depth: 0 }
      - uses: anthropics/claude-code-action@v1
        with:
          anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
          prompt: |
            /larecipe-sync-docs
            REPO: ${{ github.repository }} PR: ${{ github.event.pull_request.number }}
            Commit any documentation updates to this PR branch.
          claude_args: |
            --max-turns 20
            --allowedTools "Read,Write,Edit,Glob,Grep,Bash(git:*),Bash(php artisan larecipe:*)"
```

**Pattern B: open a separate docs PR after merge.** This is the recommended setup: Claude only edits files, and a deterministic action opens the PR.

```yaml
name: Docs sync after merge
on:
  pull_request:
    types: [closed]
    branches: [main]
concurrency: { group: docs-sync, cancel-in-progress: false }
jobs:
  sync:
    if: >
      github.event.pull_request.merged == true &&
      !contains(github.event.pull_request.labels.*.name, 'skip-docs') &&
      !startsWith(github.event.pull_request.head.ref, 'docs/sync-')
    runs-on: ubuntu-latest
    permissions: { contents: write, pull-requests: write, id-token: write }
    steps:
      - uses: actions/checkout@v6
        with: { ref: main, fetch-depth: 0 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: "8.4" }
      - run: composer install --no-interaction --prefer-dist --no-progress
      - id: affected
        run: php artisan larecipe:affected --base=${{ github.event.pull_request.base.sha }} --head=${{ github.event.pull_request.merge_commit_sha }} --github-output
      - if: steps.affected.outputs.count != '0'
        uses: anthropics/claude-code-action@v1
        with:
          anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
          prompt: |
            /larecipe-sync-docs
            Merged PR #${{ github.event.pull_request.number }}.
            Affected pages: ${{ steps.affected.outputs.pages }}
            Edit files only. Do not commit or push.
          claude_args: |
            --max-turns 25
            --allowedTools "Read,Edit,Write,Glob,Grep,Bash(git diff:*),Bash(git log:*),Bash(php artisan larecipe:*)"
      - if: steps.affected.outputs.count != '0'
        uses: peter-evans/create-pull-request@v8   # latest v8.1.1 (2026-04-10)
        with:
          branch: docs/sync-pr-${{ github.event.pull_request.number }}
          title: "docs: sync with #${{ github.event.pull_request.number }}"
          add-paths: resources/docs/**
          labels: documentation, automated
          token: ${{ secrets.DOCS_BOT_TOKEN }}   # App/PAT so CI runs on the docs PR
```

`larecipe:affected` and `--github-output` are proposed LaRecipe v3 commands, not existing APIs. The default allowed tool set for file edits in agent mode was not verified, so the example grants tools explicitly.

**Security notes** (docs/security.md, FAQ)

- Fork PRs do not receive secrets on public repos.
- `allowed_non_write_users` raises prompt-injection risk.
- Wrap untrusted PR text in delimiters.
- Keep `allowed_bots` empty to avoid loops.

---

## 5. Cross-agent standards

Research date: 2026-10-17. Everything below was checked against the cited URL or file. "Unverified" marks anything that could not be confirmed from a primary source.

### 5.1 AGENTS.md

Source: https://agents.md/

- What it is: "a simple, open format for guiding coding agents", "a README for agents". Plain Markdown, no required headings or fields.
- Location: repo root; extra `AGENTS.md` files in subdirectories for monorepos. "Agents automatically read the nearest file in the directory tree, so the closest one takes precedence."
- Governance: "AGENTS.md is now stewarded by the Agentic AI Foundation under the Linux Foundation."
- Listed supporters on agents.md: OpenAI Codex, Google Jules, Factory, Aider, Goose, OpenCode, Zed, Warp, VS Code, Devin, UiPath Autopilot, JetBrains Junie, Amp, Cursor, RooCode, Gemini CLI, Kilo Code, Phoenix, Semgrep, GitHub Copilot, Ona, Windsurf, Augment Code.

How each major agent actually consumes it:

| Agent | Reads AGENTS.md? | Details | Source |
| - | - | - | - |
| Claude Code | Yes, natively since v2.1.277 | Reads `AGENTS.md` only when there is no `CLAUDE.md`, `.claude/CLAUDE.md` or `CLAUDE.local.md` in cwd or above. If a CLAUDE.md exists, only CLAUDE.md loads unless CLAUDE.md imports it (`@AGENTS.md`) or the "Project instructions" setting is changed. `~/.claude/CLAUDE.md`, managed CLAUDE.md and `.claude/rules/` keep loading alongside. | https://code.claude.com/docs/en/memory#agents-md |
| OpenAI Codex | Yes (primary format) | Global `~/.codex/AGENTS.override.md` then `~/.codex/AGENTS.md`; then walks git root to cwd checking `AGENTS.override.md`, `AGENTS.md`, then `project_doc_fallback_filenames` (in `~/.codex/config.toml`). Concatenated root-down, closer files win. Cap: `project_doc_max_bytes` (32 KiB default). | https://learn.chatgpt.com/docs/agent-configuration/agents-md |
| GitHub Copilot | Yes | `AGENTS.md` anywhere ("nearest wins"); or `CLAUDE.md` / `GEMINI.md` at root as alternatives. Also `.github/copilot-instructions.md` (repo-wide) and `.github/instructions/NAME.instructions.md` with `applyTo:` glob frontmatter. Cloud agent uses all three types; code review uses repo-wide and path-specific. | https://docs.github.com/en/copilot/how-tos/configure-custom-instructions/add-repository-instructions |
| Cursor | Yes, root and nested | Plus `.cursor/rules/*.mdc` (frontmatter `description`, `globs`, `alwaysApply`; plain `.md` ignored). Remote rules only via plugins + marketplace. | https://cursor.com/docs/context/rules |
| Gemini CLI | Opt-in | Default context file is `GEMINI.md`. To read AGENTS.md: `settings.json` -> `"context": { "fileName": ["AGENTS.md", "GEMINI.md"] }`. Supports `@./file.md` imports. | https://geminicli.com/docs/cli/gemini-md/ |

Practical rule for a package installer: write one canonical `AGENTS.md` block and either (a) also write `CLAUDE.md` containing `@AGENTS.md`, or (b) write the same block to both. Laravel Boost already manages these files (see section 3), so LaRecipe should ship Boost guidelines rather than editing these files itself.

### 5.2 Agent Skills open standard (agentskills.io)

Spec: https://agentskills.io/specification. Originally developed by Anthropic, "released as an open standard" (https://agentskills.io/). Repo: https://github.com/agentskills/agentskills.

Directory layout:

```
skill-name/
├── SKILL.md          # Required: YAML frontmatter + Markdown body
├── scripts/          # Optional: executable code
├── references/       # Optional: docs loaded on demand (REFERENCE.md, etc.)
├── assets/           # Optional: templates, images, data
└── ...
```

Frontmatter fields (exact constraints from the spec):

| Field | Required | Constraints |
| - | - | - |
| `name` | Yes | 1-64 chars, lowercase `a-z`, `0-9`, `-`; no leading/trailing hyphen; no `--`; must match parent directory name |
| `description` | Yes | 1-1024 chars; say what it does AND when to use it, with trigger keywords |
| `license` | No | License name or bundled file reference |
| `compatibility` | No | 1-500 chars; environment requirements |
| `metadata` | No | map of string -> string |
| `allowed-tools` | No | Space-separated, e.g. `Bash(git:*) Bash(jq:*) Read`. Experimental |

```markdown
---
name: larecipe-docs
description: Write and update LaRecipe documentation pages in docs/. Use when adding a feature, changing a public API, or when the user mentions docs, guides, or the docs site.
license: MIT
metadata:
  author: larecipe
  version: "3.0"
---

# Writing LaRecipe docs
...
See [frontmatter reference](references/FRONTMATTER.md).
```

Progressive disclosure: metadata (~100 tokens) loaded at startup for all skills; body (<5000 tokens recommended, keep SKILL.md under 500 lines) loaded on activation; `scripts/`, `references/`, `assets/` loaded on demand. Keep file references one level deep. Validator: `skills-ref validate ./my-skill`.

Client-side discovery convention (https://agentskills.io/client-implementation/adding-skills-support.md): scan `<project>/.<client>/skills/`, `<project>/.agents/skills/`, `~/.<client>/skills/`, `~/.agents/skills/`. "The `.agents/skills/` paths have emerged as a widely-adopted convention for cross-client skill sharing." Some clients also scan `.claude/skills/`. Universal precedence convention: project overrides user (Claude Code is an exception, see table). The spec itself does not mandate locations.

Where each agent looks for skills:

| Agent | Project paths | User paths | Notes / source |
| - | - | - | - |
| Claude Code | `.claude/skills/<name>/SKILL.md` (cwd and parents up to repo root; nested subdirs load lazily) | `~/.claude/skills/` | Personal overrides project. `.claude/commands/*.md` merged into skills. No mention of `.agents/skills/`. https://code.claude.com/docs/en/skills |
| Codex | `.agents/skills` (cwd and parents up to repo root) | `~/.agents/skills`, admin `/etc/codex/skills` | Explicit `$skill-name`; optional `agents/openai.yaml` (`policy.allow_implicit_invocation`). https://learn.chatgpt.com/docs/build-skills |
| Cursor | `.agents/skills/`, `.cursor/skills/`; legacy `.claude/skills/`, `.codex/skills/` | `~/.agents/skills/`, `~/.cursor/skills/`, legacy `~/.claude/skills/`, `~/.codex/skills/` | Extra fields `paths`, `disable-model-invocation`, `icon`, `color`. https://cursor.com/docs/context/skills |
| GitHub Copilot | `.github/skills`, `.claude/skills`, `.agents/skills` | `~/.copilot/skills`, `~/.agents/skills` | Cloud agent, code review, Copilot CLI, VS Code and JetBrains agent mode. https://docs.github.com/en/copilot/concepts/agents/about-agent-skills |
| Gemini CLI | `.gemini/skills/`, `.agents/skills/` (highest precedence) | `~/.gemini/skills/`, `~/.agents/skills/` | `activate_skill` tool with user approval; `/skills`, `gemini skills install`. https://geminicli.com/docs/cli/skills/ |
| Laravel Boost | Listed as a client on agentskills.io | | https://laravel.com/docs/12.x/boost#agent-skills |

Other listed clients (agentskills.io home): Junie, OpenCode, OpenHands, Amp, Goose, VS Code, Factory, Roo Code, Kiro, Letta, TRAE, Spring AI, Mistral Vibe, Tabnine, Ona, Qodo, Snowflake Cortex Code, Databricks Genie Code, and more.

Implication: writing a skill to both `.claude/skills/<name>/` and `.agents/skills/<name>/` covers Claude Code, Codex, Cursor, Copilot, Gemini CLI and OpenCode. Writing only `.claude/skills/` covers Claude Code, Cursor and Copilot but not Codex or Gemini.

### 5.3 Publishing skills over HTTP: `/.well-known/agent-skills/index.json`

Source: https://github.com/cloudflare/agent-skills-discovery-rfc (Status: Draft, v0.2.0, updated 2026-03-12).

- Publishers MUST serve `/.well-known/agent-skills/index.json`. v0.2.0 renamed the path from `/.well-known/skills/` (v0.1.0).
- Index shape:

```json
{
  "$schema": "https://schemas.agentskills.io/discovery/0.2.0/schema.json",
  "skills": [
    {
      "name": "larecipe-authoring",
      "type": "skill-md",
      "description": "Write LaRecipe docs pages. Use when ...",
      "url": "/.well-known/agent-skills/larecipe-authoring/SKILL.md",
      "digest": "sha256:<64 lowercase hex>"
    },
    {
      "name": "larecipe-api",
      "type": "archive",
      "description": "...",
      "url": "/.well-known/agent-skills/larecipe-api.tar.gz",
      "digest": "sha256:<64 lowercase hex>"
    }
  ]
}
```

- `type` is `"skill-md"` (single file) or `"archive"` (skill with resources). `digest` is SHA-256 of the artifact. Clients "SHALL NOT execute scripts by default".
- In the wild (verified with curl on 2026-10-17):
  - `docs.stripe.com` sends `Link: </.well-known/skills/index.json>; rel="service-meta"` (v0.1.0 path, `files` array format).
  - Mintlify docs send `Link: ... </docs/.well-known/agent-skills/index.json>; rel="agent-skills"`.

### 5.4 llms.txt (v2, August 2026) and llms-full.txt

Sources: https://llmstxt.org/ , https://github.com/AnswerDotAI/llms-txt (README "The /llms.txt file, v2", date-modified 2026-08-10, and `nbs/changes.qmd`).

Format (in order):
1. Optional BOM
2. `# H1` project/site name. The only required section.
3. `> blockquote` short summary
4. Zero or more non-heading Markdown sections with details
5. Zero or more `## H2` sections, each a "file list": `- [name](url): optional notes`
6. `## Optional` section by convention for skippable links

```markdown
# LaRecipe

> LaRecipe is a Laravel package that turns Markdown in your repo into a documentation site, an MCP server, and agent skills.

Use the 3.x pages. Code samples assume Laravel 12+.

## Docs

- [Installation](https://example.com/docs/3.x/installation.md): composer install, publish config
- [Configuration](https://example.com/docs/3.x/configuration.md): every config key

## Optional

- [Changelog](https://example.com/docs/3.x/changelog.md)
```

What changed in v2 (changes.qmd):
- Location: root or any subpath; "a file covers the pages under its path, and the most specific file applies" (e.g. `/docs/llms.txt`, `/docs/3.x/llms.txt`).
- Per-page markdown at the same URL with `.md` appended (`page.html.md`) or extension replaced (`page.md`); URLs without file names use `index.html.md` or `index.md`.
- Discovery via standard link relations, as HTML `<link>` or HTTP header:
  `Link: </docs/page.md>; rel="alternate"; type="text/markdown", </docs/llms.txt>; rel="describedby"`
- `llms_txt2ctx` / `llms-ctx.txt` expansion tooling dropped from the proposal; the `Optional` section no longer has mechanical meaning.
- Explicitly rejected `/.well-known/` as the location (path-scoped files cannot live there).
- Accept-header content negotiation is NOT part of llms.txt v2.

llms-full.txt: not in the llms.txt spec (v1 or v2). It is a community/platform convention: one file concatenating the full Markdown of every page. Implementations: Mintlify ("Each page appears as its title, source URL, description, and full Markdown content", https://www.mintlify.com/docs/ai/llmstxt), GitBook (`/llms-full.txt`, includes hidden pages, https://gitbook.com/docs/ai-and-search/llm-ready-docs), Cloudflare (`/llms-full.txt` plus per-product `/workers/llms.txt`, https://developers.cloudflare.com/docs-for-agents/), Vercel (`/docs/llms-full.txt`).

Tooling signal: Chrome Lighthouse has an "agentic browsing" category with an llms.txt audit (404 = N/A; server error = fail) alongside WebMCP audits. https://developer.chrome.com/docs/lighthouse/agentic-browsing/llms-txt

PHP library listed by the spec: `raphaelstolt/llms-txt-php` (read/write llms.txt).

### 5.5 Serving Markdown: `.md` suffix and `Accept: text/markdown`

Who does what (verified 2026-10-17):

| Site / platform | `.md` URL | `Accept: text/markdown` | Headers / extras | Source |
| - | - | - | - | - |
| Cloudflare "Markdown for Agents" (edge feature, any zone) | n/a | Yes, converts origin HTML at the edge | Response `content-type: text/markdown; charset=utf-8`, adds `Accept` to `Vary`, default `Content-Signal: ai-train=yes, search=yes, ai-input=yes` unless origin sets one; drops `ETag`, `Last-Modified`; 6 MiB HTML limit; Pro/Business/Enterprise. `x-markdown-tokens` header no longer emitted after an Oct 2026 change. | https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/ , https://developers.cloudflare.com/changelog/post/2026-10-09-markdown-for-agents-in-process-conversion/ |
| Cloudflare docs | `/index.md` appended | Yes | `/llms.txt`, `/llms-full.txt`, per-product llms.txt | https://developers.cloudflare.com/docs-for-agents/ |
| Vercel docs | `https://vercel.com/docs/functions.md` | Yes; also returns Markdown to recognized AI agents with no format preference | `Vary: Accept`, CDN keys on Accept; `rel="alternate" type="text/markdown"`; Markdown responses start with YAML frontmatter (`title`, `url`, `canonical_url`, `last_updated`, `type`, `prerequisites`, `related`, `summary`); `.graph.md` per page; `/.well-known/ai-catalog.json`; RFC 9727 `/.well-known/api-catalog` | https://vercel.com/docs/agent-resources/markdown-access |
| Stripe docs | `docs.stripe.com/payments/checkout.md` | Yes | `Vary: Accept`; `Link: </.well-known/skills/index.json>; rel="service-meta"` | curl, 2026-10-17 |
| Mintlify (all hosted sites) | Yes, llms.txt links use `.md` | Yes (`vary: accept, ..., User-Agent` observed) | `Link` header with `rel="llms-txt"`, `rel="llms-full-txt"`, `rel="api-catalog"`, `rel="mcp-server-card"`, `rel="agent-card"`, `rel="agent-skills"`; `X-Llms-Txt: /docs/llms.txt` | https://www.mintlify.com/docs/ai/llmstxt , curl |
| GitBook | Yes, append `.md` | unverified | `/llms.txt`, `/llms-full.txt` | https://gitbook.com/docs/ai-and-search/llm-ready-docs |
| laravel.com docs | Yes, `/docs/12.x/routing.md` returns `text/markdown` | No (returns HTML, `vary: X-Inertia`) | no root `/llms.txt` (404) | curl, 2026-10-17 |
| Next.js | `markdownAgents` content-negotiation option proposed in PR vercel/next.js#98807 | merge status unverified | | https://github.com/vercel/next.js/pull/98807 |

Client side: Claude Code WebFetch "sets a `User-Agent` header beginning with `Claude-User`, and an `Accept` header that prefers Markdown over HTML so servers that support content negotiation can return Markdown directly" (https://code.claude.com/docs/en/tools-reference). Third-party reports say OpenCode also sends `Accept: text/markdown` (unverified against OpenCode source).

Note the rel-value split: llms.txt v2 recommends registered relations (`alternate` + `type="text/markdown"`, `describedby`); Mintlify uses its own unregistered rels (`llms-txt`, `agent-skills`, ...). Emitting both is cheap.

Laravel implementation sketch (one route, two representations):

```php
// Same URL, negotiated. Also register "{page}.md" explicitly.
Route::get('/docs/{version}/{page?}', function (Request $request, string $version, ?string $page = null) {
    $doc = Docs::find($version, $page ?? 'index');

    $headers = [
        'Vary' => 'Accept',
        'Link' => sprintf('<%s>; rel="alternate"; type="text/markdown", <%s>; rel="describedby"',
            $doc->markdownUrl(), url("/docs/{$version}/llms.txt")),
    ];

    if (str_contains($request->header('Accept', ''), 'text/markdown')) {
        return response($doc->agentMarkdown(), 200, $headers + ['Content-Type' => 'text/markdown; charset=utf-8']);
    }

    return response()->view('larecipe::page', ['doc' => $doc], 200, $headers);
})->where('page', '.*');
```

Caveat: `str_contains` on Accept is a simplification; real negotiation should respect q-values (Claude Code sends Markdown with higher q than HTML; browsers never list `text/markdown`). Symfony's `Request::getAcceptableContentTypes()` returns them sorted by quality.

### 5.6 Docs-as-MCP: what exists now

There is no ratified "docs MCP" standard. The de facto shapes:

Mintlify (https://www.mintlify.com/docs/ai/model-context-protocol)
- Auto-hosted at `https://<docs>/mcp`; authenticated sites `/authed/mcp` (OAuth at `/authed/mcp/oauth/*`).
- Tools: Search (site-specific name such as `search_your_docs`, input `{ "query": string }`, optional `version`, `language`), "Query docs filesystem" (navigate a virtual filesystem with shell-style commands; exact command set unverified), Submit feedback. Exposes `skill.md` files as MCP resources.
- Discovery: `/.well-known/mcp`, `/.well-known/mcp/server-card.json`, `/.well-known/mcp/server-cards.json`. "Tool names are site-specific, so use the value returned by the discovery endpoint rather than hardcoding it."
- Rate limits: 5,000 req/h per IP; 10,000 req/h per site for search and for filesystem.

GitBook (https://gitbook.com/docs/ai-for-your-readers/mcp-servers-for-published-docs)
- Every published site: `{site}/~gitbook/mcp` (public), `{site}/~gitbook/mcp/auth` (mixed auth). Read-only, latest published version only. Tool names unverified.

Context7 (https://github.com/upstash/context7 , https://context7.com/docs/api-guide)
- Remote MCP: `https://mcp.context7.com/mcp`, `Authorization: Bearer <key>`.
- Tools: `resolve-library-id` (`query` required, `libraryName` required) and `query-docs` (`libraryId` required, `query` required, `language` optional). The older `get-library-docs` name is superseded.
- REST: `GET https://context7.com/api/v2/libs/search?libraryName=&query=`, `GET /api/v2/context?libraryId=&query=&language=&type=`, `GET /api/v3/search?query=&library=`, `POST /api/v1/refresh`. Library IDs look like `/vercel/next.js` or `/vercel/next.js@v16.2.9`.
- Library owners commit `context7.json`:

```json
{
  "$schema": "https://context7.com/schema/context7.json",
  "projectTitle": "LaRecipe",
  "description": "Docs-as-code package for Laravel",
  "folders": ["docs"],
  "excludeFolders": ["vendor", "node_modules"],
  "excludeFiles": [],
  "rules": ["Use the 3.x config keys", "Docs live in docs/{version}/"],
  "previousVersions": [{ "tag": "v2.6.0" }]
}
```

  Other fields: `branch`, `branchVersions`. `excludeFolders` wins over `folders`. https://context7.com/docs/library-owners
- Also ships `ctx7` CLI ("CLI + Skills mode", Node >= 22.13).

MCP Server Cards (discovery metadata, not a docs API)
- SEP-2127, prototyped in https://github.com/modelcontextprotocol/experimental-ext-server-card: "Experimental ... not an accepted or official MCP extension". Recommended location `GET <streamable-http-url>/server-card`; describes identity and remote endpoints only, intentionally no tool listings. Mintlify's `/.well-known/mcp/server-card.json` predates this and the final path may move.

WebMCP (in-page tools for browser agents)
- W3C Community Group draft (16 Oct 2026): `document.modelContext.registerTool({ name, description, inputSchema, execute })`. https://webmachinelearning.github.io/webmcp/ . Origin-trial and declarative-form details are only in third-party posts (unverified). Lighthouse already audits WebMCP tools.

Consensus "docs MCP" tool surface, derived from the above: a `search` tool (`query`, optional `version`/`language`) returning snippets with titles and URLs, plus a fetch/read tool (by path or library id) returning Markdown. LaRecipe can match that with laravel/mcp (section 1).

### 5.7 What this means for LaRecipe v3

1. Serve every page as Markdown at `{url}.md` AND via `Accept: text/markdown` with `Vary: Accept`. Add `Link` headers with both llms.txt v2 rels and Mintlify-style rels.
2. Generate `/docs/llms.txt` per version (path-scoped per v2) and `/docs/llms-full.txt` (convention). Optionally `/docs/{version}/llms.txt`.
3. Publish skills from the docs site at `/.well-known/agent-skills/index.json` (RFC v0.2.0 shape with sha256 digests), and install them into user repos at `.claude/skills/` and `.agents/skills/` (or via Boost).
4. Ship a docs MCP server with `search-docs` + `read-doc` tools; consider a server card behind a feature flag since the spec is still experimental.
5. Ship a `context7.json` generator so package authors using LaRecipe get indexed by Context7.

---

## 6. Inertia v3 and React UI in Laravel packages

### 6.1 Inertia v3: versions, requirements, what changed

**Release facts (GitHub releases API, checked 2026-10-17)**

| Item | Value | Source |
|---|---|---|
| `inertiajs/inertia-laravel` v3.0.0 | tagged 2026-03-25 | https://github.com/inertiajs/inertia-laravel/releases/tag/v3.0.0 |
| Latest `inertiajs/inertia-laravel` | v3.5.1 (2026-10-01) | https://github.com/inertiajs/inertia-laravel/releases |
| Latest client (`@inertiajs/core`, `/react`, `/vue3`, `/svelte`, `/vite`) | v3.9.1 (2026-10-09) | https://github.com/inertiajs/inertia/releases |
| v2 still maintained | inertia-laravel v2.0.28 (2026-09-25), client v2.3.29 (2026-10-09) | same release pages |
| nashra-app local | inertia-laravel v3.0.6, `@inertiajs/react` 3.0.3, react 19.1.0 | `/Users/saleemhadad/Code/nashra-app/vendor/composer/installed.json`, `node_modules/@inertiajs/react/package.json` |

**Requirements**

- PHP `^8.2.0`, `laravel/framework` `^11.35|^12.0|^13.0` on main (`^11.0|^12.0|^13.0` in the v3.0.6 vendor copy), `symfony/console` `^7.0|^8.0`. Source: `vendor/inertiajs/inertia-laravel/composer.json` and https://github.com/inertiajs/inertia-laravel/blob/3.x/composer.json
- `composer.json` declares `"conflict": {"laravel/boost": "<2.2.0"}` (vendor file above).
- React adapter: peer `react ^19.0.0`, `react-dom ^19.0.0` (React 18 dropped). Svelte 5 only (runes). Output is ES2022 ESM only, no CommonJS `require()`. Source: `node_modules/@inertiajs/react/package.json`, https://inertiajs.com/docs/v3/getting-started/upgrade-guide
- `@inertiajs/core` 3.x dependencies: `es-toolkit`, `laravel-precognition ^2`, `@jridgewell/trace-mapping`; `axios` is now an optional peer. Source: `node_modules/@inertiajs/core/package.json`
- SSR server requires Node.js 22+ (Bun supported through `inertia.ssr.runtime`). Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

**New in v3** (upgrade guide + release notes: https://inertiajs.com/docs/v3/getting-started/upgrade-guide, https://laravel-news.com/inertia-3-0-0)

- Axios removed; built-in XHR client with interceptors (Axios adapter optional). `qs` and `lodash-es` no longer bundled.
- `useHttp` hook for non-navigation requests; `router.optimistic(...)` / optimistic updates on router, `useForm`, `useHttp` with automatic rollback.
- `@inertiajs/vite` plugin: automatic page resolution and SSR wiring; `createInertiaApp()` can be called with no arguments.
- SSR works in `vite dev` without a separate Node process (Laravel posts to `{hot-url}/__inertia_ssr`).
- Per-route SSR disable: `$withoutSsr` middleware property, `Inertia::withoutSsr([...])`, `Inertia::disableSsr()` (added v3.0.3).
- Blade components `<x-inertia::head />` and `<x-inertia::app />` replace `@inertiaHead` / `@inertia` (directives still registered).
- Instant visits, layout props (`useLayoutProps`, `setLayoutProps`, default layout option), backed-enum component names in `Inertia::render()`, nested prop types with dot notation, `Inertia::handleExceptionsUsing()`, fragment preservation across redirects, `expose_shared_prop_keys` (page carries `sharedProps`).
- Later 3.x: deferred props `rescue` option (laravel 3.1.0), Inertia DevTools support (laravel 3.2.0), configurable SSR HTTP request and `inertia:stop-ssr --graceful` (laravel 3.4.0), BigInt props (laravel 3.5.0 / client 3.8.0), `serverHead` option so the server can send `<title>`/`<meta>` strings as a prop (client 3.5.0, https://github.com/inertiajs/inertia/pull/3161), `<WhenMounted>` (client 3.8.0).

**Breaking changes worth knowing**

- Initial page object is now `<script data-page="app" type="application/json">...</script><div id="app"></div>`; the legacy `data-page` attribute on the div is unsupported (`pages.use_data_attribute_for_initial_page` config removed).
- Events renamed: `invalid` to `httpException`, `exception` to `networkError`. `router.cancel()` replaced by `router.cancelAll()`.
- `Inertia::lazy()` / `LazyProp` removed (use `Inertia::optional()`); testing traits `Has`, `Matching`, `Debugging` removed.
- Head attribute `inertia` renamed to `data-inertia`. React layouts: `Page.layout = [Layout]` (arrow functions no longer accepted).
- Config restructured (`pages.*` vs `testing.*`).

**Server API confirmed from source** (`vendor/inertiajs/inertia-laravel/src`)

```php
// config/inertia.php (v3.0.6)
'ssr' => [
    'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),   // NOTE: true by default
    'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),
    'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),
    'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
    'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
    'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),
],
'pages' => ['ensure_pages_exist' => false, 'paths' => [resource_path('js/pages')], 'extensions' => [...]],
'testing' => ['ensure_pages_exist' => true],
'expose_shared_prop_keys' => true,
'history' => ['encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false)],
```

- `ServiceProvider.php`: `singleton(ResponseFactory::class)`, `singleton(HttpGateway::class)`, `scoped(SsrState::class)`; registers `Blade::componentNamespace('Inertia\\View\\Components', 'inertia')` and `@inertia` / `@inertiaHead`.
- `Middleware.php`: `protected $rootView = 'app'`, `protected $withoutSsr = []`; `version()` hashes `config('app.asset_url')`, else `public/build/manifest.json`, else `public/mix-manifest.json` (xxh128); `handle()` calls `Inertia::version()`, `Inertia::share($this->share($request))`, `shareOnce()`, `Inertia::setRootView($this->rootView($request))`, then applies `withoutSsr` to the gateway.
- `ResponseFactory.php` public API includes `setRootView`, `share`, `getShared`, `flushShared`, `version`, `getVersion`, `transformComponentUsing`, `disableSsr(Closure|bool)`, `withoutSsr(array|string)`, `optional`, `defer`, `merge`, `deepMerge`, `always`, `scroll`, `once`, `shareOnce`, `render`, `location`, `handleExceptionsUsing`, `flash`, `back`.
- `Response.php`: `with()`, `withViewData()`, `rootView(string)` (per-response root view), `flash()`.
- `Ssr/HttpGateway.php::dispatch()`: if `Vite::isRunningHot()` it POSTs to `{public/hot url}/__inertia_ssr`, otherwise to `{inertia.ssr.url}/render`; on failure fires `Inertia\Ssr\SsrRenderFailed` and falls back to client rendering unless `throw_on_error`.
- Commands: `inertia:start-ssr {--runtime=}`, `inertia:stop-ssr`, `inertia:check-ssr`, `inertia:middleware`.
- Inertia itself ships Boost guidelines at `vendor/inertiajs/inertia-laravel/resources/boost/guidelines/core.blade.php` (see section 3).

Root view (https://inertiajs.com/docs/v3/installation/server-side-setup):

```blade
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/js/app.js')
    <x-inertia::head />
</head>
<body>
    <x-inertia::app />   {{-- accepts id="..." ; renders SSR body or the JSON script + empty div --}}
</body>
</html>
```

Client (https://inertiajs.com/docs/v3/installation/client-side-setup):

```jsx
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'

createInertiaApp({
  id: 'larecipe',   // must match <x-inertia::app id="larecipe" />
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx')
    return pages[`./Pages/${name}.tsx`]()
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})
```

### 6.2 How established packages ship UI without touching the host frontend

| Package (version checked) | UI tech | Asset delivery | Evidence |
|---|---|---|---|
| Horizon v5.50 (vendor 5.50.0, latest 5.50.1) | Vue SPA + vue-router over a JSON API, Blade shell | Inlines `dist/app.js` (1.4 MB) and three CSS files into every HTML response with `Horizon::css()` / `Horizon::js()` (`file_get_contents(__DIR__.'/../dist/...')`). `horizon:publish` is deprecated: "Horizon no longer publishes its assets". CSP via `Horizon::cspNonce($nonce)`. | `vendor/laravel/horizon/src/Horizon.php` lines 185-230, `resources/views/layout.blade.php`, `src/Console/PublishCommand.php` |
| Telescope v5.26.0 | Vue SPA + JSON API | Same inline pattern: `Telescope::css()` / `Telescope::js()` reading `dist/` | https://github.com/laravel/telescope/blob/5.x/src/Telescope.php (`css()` ~line 825, `js()` ~line 853) |
| Pulse v1.8.1 | Livewire + Blade cards | `Pulse::css()` inlines `dist/pulse.css`; `Pulse::js()` inlines `vendor/livewire/livewire/dist/livewire.js` plus `dist/pulse.js`; layout uses `@livewireStyles` and `@livewireScriptConfig` | https://github.com/laravel/pulse/blob/1.x/src/Pulse.php (lines 88, 498-530), `resources/views/components/pulse.blade.php` |
| Nightwatch v1.30.3 (vendor 1.24.4) | No local UI. Dashboard is the hosted SaaS | Package ships a PHP agent (`agent/build/agent.phar`) and sensors only; no Blade/JS/CSS in the package | `vendor/laravel/nightwatch/` (no `.blade.php`, `.js`, `.css` files) |
| Log Viewer (opcodesio) v3.24.2 | Vue SPA | Hybrid: if `public/{log-viewer.assets_path}/mix-manifest.json` exists it emits `<link>`/`<script src>` with `mix()` cache busting, else inlines `LogViewer::css()` / `LogViewer::js()` from vendor. `assetsAreCurrent()` is `@deprecated Publishing assets is no longer required. Assets are now served directly from the vendor directory.` | https://github.com/opcodesio/log-viewer/blob/main/resources/views/index.blade.php, `src/LogViewerService.php` lines 347-400 |
| Livewire v4.4.7 | Livewire runtime JS | Serves `dist/livewire(.min).js` through a PHP route at `/livewire-{first 8 chars of sha256(app.key.'livewire-endpoint')}/livewire.min.js` using `Utils::pretendResponseIsFile()` which sets `Cache-Control: public, max-age=31536000`, `Expires` +1 year, `Last-Modified`, and answers 304. Optional publish to `public/vendor/livewire` with `manifest.json` versioning. | https://github.com/livewire/livewire/blob/main/src/Mechanisms/FrontendAssets/FrontendAssets.php, `src/Drawer/Utils.php`, `src/Mechanisms/HandleRequests/EndpointResolver.php` |
| Filament v5.10.2 (docs 4.x, same API) | Livewire + Alpine | `FilamentAsset::register([Css::make('id', path), Js::make('id', path), AlpineComponent::make(...)], package: 'vendor/name')`; `php artisan filament:assets` copies into `public/css/{pkg}`, `public/js/{pkg}`, `public/fonts/{pkg}`; URL is `asset($path).'?v='.$version` where version is sha256 of the Composer reference (`Composer\InstalledVersions`). `filament:upgrade` in `post-autoload-dump` re-runs it; docs recommend gitignoring the published dirs. `->loadedOnRequest()` for lazy assets. | https://filamentphp.com/docs/4.x/advanced/assets, https://github.com/filamentphp/filament/blob/5.x/packages/support/src/Assets/Asset.php, `.../Commands/AssetsCommand.php` |
| Wayfinder v0.1.23 (still 0.x, next version on `next` branch) | Not a UI | Codegen into the host stack: `php artisan wayfinder:generate [--path=] [--skip-actions] [--skip-routes] [--with-form]` writes TS into `resources/js/{wayfinder,actions,routes}`; Vite plugin `@laravel/vite-plugin-wayfinder` runs it on build and file change | https://github.com/laravel/wayfinder |
| laravel/ui v4.6 | Scaffolding presets | Copies stubs into host `resources/js` and overwrites `vite.config.js` (eject pattern, forces a stack) | `vendor/laravel/ui/src/Presets/React.php` lines 51-78 |

Takeaways:

1. No first-party Laravel package ships an Inertia UI. Every self-contained dashboard (Horizon, Telescope, Log Viewer) is "Blade shell + prebuilt SPA bundle + JSON endpoints", with zero host build involvement.
2. Asset delivery has converged on "works with no publish step": inline (Horizon/Telescope/Pulse), PHP route with long cache (Livewire), or publish-if-present with inline fallback (Log Viewer). Filament is the exception that requires a publish step but automates it via `post-autoload-dump`.
3. Inline is fine for an internal admin tool but wrong for a public docs site: a 1 MB+ bundle re-sent on every HTML response, no HTTP caching, hurts LCP/TTFB. LaRecipe should copy Livewire's route approach (cacheable, hashed URLs, no publish step) with optional publishing for CDN/static serving.

### 6.3 Recommended asset delivery for a docs package

Laravel's `Illuminate\Foundation\Vite` is a container singleton (`FoundationServiceProvider::$singletons`) with `useBuildDirectory()`, `useHotFile()`, `useManifestFilename()`, `createAssetPathsUsing()`, `useCspNonce()`, `useIntegrityKey()`, `withEntryPoints()`, `asset()`, `content()`, `manifestHash()`; `manifestPath()` is `protected` and hardcodes `public_path($buildDirectory.'/'.$manifestFilename)` (`vendor/laravel/framework/src/Illuminate/Foundation/Vite.php` lines 147-372, 872-972). So a package that wants hashed Vite output served from `vendor/` without publishing can (design sketch, not an established pattern):

```php
// src/Support/LaRecipeVite.php
final class LaRecipeVite extends \Illuminate\Foundation\Vite
{
    protected function manifestPath($buildDirectory)
    {
        // read the manifest that ships inside the package
        return dirname(__DIR__, 2).'/dist/.vite/manifest.json';
    }
}

// in the root Blade view (package-only request, so no global side effects)
{{ (new \LaRecipe\Support\LaRecipeVite)
      ->useHotFile(dirname(__DIR__, 2).'/dist/hot')           // package dev server
      ->createAssetPathsUsing(fn ($path) => route('larecipe.asset', ['path' => $path]))
      ->withEntryPoints(['resources/js/app.tsx']) }}

// routes: hashed filenames, so cache "immutable"
Route::get('larecipe-assets/{path}', AssetController::class)
    ->where('path', '.*')->name('larecipe.asset');
// controller: realpath() inside dist/, reject traversal, set
// Cache-Control: public, max-age=31536000, immutable ; optional .br/.gz siblings
```

Plus `php artisan larecipe:publish-assets` copying `dist/` to `public/vendor/larecipe` and switching `createAssetPathsUsing` to `asset()` when the published manifest hash matches (Log Viewer / Livewire detection pattern).

### 6.4 Can a package use Inertia internally without conflicting with the host?

Technically yes, but the server adapter is global per request. Facts from source:

- `Inertia\ResponseFactory` is a singleton; shared props, root view, version, and SSR gateway state are process/request global (`ServiceProvider.php` line 28-31, `ResponseFactory.php`).
- Host apps append `HandleInertiaRequests` to the `web` group (official setup). If package routes use `web`, the host middleware runs first and calls `Inertia::share(...)`. Those shared props (including closures) are resolved when the package renders. Concrete risk in nashra-app: `app/Http/Middleware/HandleInertiaRequests.php::share()` runs tenant/role lookups and contains `fn () => $request->session()->pull('signup_conversion')` and `pull('language_suggestion')`; a package Inertia page rendered under that middleware would consume those one-shot values and ship the host's `auth.user` payload into the docs page.
- SSR is `enabled => true` by default in v3. `HttpGateway::dispatch()` would POST the package's page object to the host SSR server, or to the host's Vite dev server at `/__inertia_ssr` when `public/hot` exists. The host bundle cannot resolve the package's components, so you get `SsrRenderFailed` and client-side fallback (or an exception when `INERTIA_SSR_THROW_ON_ERROR=true`).
- Version: whichever middleware runs last wins. The host's `version()` hashes `public/build/manifest.json`, which is unrelated to the package bundle.
- Composer coupling: `require inertiajs/inertia-laravel ^3` blocks every host still on `^2` (v2 is still being patched) and inherits `conflict laravel/boost <2.2.0`.
- Client side is not a conflict: the package bundle carries its own `@inertiajs/react` + React 19 and runs on its own pages; moving between host and package pages is a full page load.

If you use it anyway, isolate it:

```php
// LaRecipe\Http\Middleware\HandleLaRecipeInertia
class HandleLaRecipeInertia extends \Inertia\Middleware
{
    protected $rootView = 'larecipe::app';

    public function version(Request $request): ?string
    {
        return hash_file('xxh128', dirname(__DIR__, 3).'/dist/.vite/manifest.json');
    }

    public function handle(Request $request, Closure $next)
    {
        Inertia::flushShared();   // drop anything the host shared
        Inertia::disableSsr();    // never hit the host SSR server
        return parent::handle($request, $next);
    }
}

// Route group that avoids the host's 'web' group (so the host share() never runs)
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    HandleLaRecipeInertia::class,
])->prefix(config('larecipe.path'))->group(...);
```

Caveat: `flushShared()` only clears what was shared before it runs, and shared closures from the host never execute if the host middleware never runs, which is why avoiding the `web` group matters more than flushing. `->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class)` works only if the host used that conventional class name (not guaranteed).

Lighter alternative: skip `inertiajs/inertia-laravel` entirely and speak the protocol yourself (it is small: initial HTML with `<script type="application/json">` page object; `X-Inertia: true` requests get JSON `{component, props, url, version}`; `409` + `X-Inertia-Location` on version mismatch; `X-Inertia-Partial-Component` / `X-Inertia-Partial-Data` for partial reloads; https://inertiajs.com/docs/v3/core-concepts/the-protocol), using only the `@inertiajs/react` client in the prebuilt bundle. This removes the Composer coupling and the shared-state leaks. Effort and edge cases (deferred props, history encryption, once props) are unverified.

For a docs site, simpler still: Horizon-style "Blade shell + React bundle + JSON endpoints", or server HTML with React islands (6.5). Inertia's main benefits (forms, validation errors, redirects, partial reloads) matter little for read-mostly docs.

### 6.5 SEO and non-JS clients (LLM crawlers, coding agents)

Facts:

- Vercel's crawler analysis (2024-12-17): "none of the major AI crawlers currently render JavaScript", naming OAI-SearchBot, ChatGPT-User, GPTBot, ClaudeBot, Meta-ExternalAgent, Bytespider, PerplexityBot. Gemini (via Googlebot) and AppleBot do render JS. ChatGPT and Claude crawlers fetch JS files (11.50% / 23.84% of requests) but do not execute them. https://vercel.com/blog/the-rise-of-the-ai-crawler (2024 data; current behaviour unverified but no public reversal found).
- Inertia v3 without SSR sends, for the first visit, only `<script data-page="app" type="application/json">{page json}</script><div id="app"></div>` (`vendor/inertiajs/inertia-laravel/src/View/Components/App.php`). The content exists only as JSON props. Non-JS clients see no rendered article; whether any crawler parses that JSON is unverified and should not be relied on.
- Inertia SSR fixes that but requires `vite build --ssr`, a long-running Node 22+ process (`php artisan inertia:start-ssr`, default port 13714, optional `cluster: true`), and supervision in production. The host's SSR server renders only the host's bundle, so a package would need its own SSR process and port. That is an unreasonable requirement for a docs package installed into arbitrary apps (shared hosting, Forge, Vapor, Cloud).

Options compared:

| Approach | Non-JS / LLM crawlers | SEO | Host requirements | Fit for LaRecipe |
|---|---|---|---|---|
| Inertia CSR only | Empty div plus JSON blob | Googlebot renders it (delayed); AI crawlers mostly see nothing | None beyond PHP | Poor |
| Inertia + SSR | Full HTML | Good | Node 22+ process, SSR build, a second SSR server for the package | Poor (ops burden) |
| Blade shell + SPA + JSON API (Horizon style) | Empty shell | Poor | None | Fine for admin, poor for public docs |
| PHP-rendered HTML first, React islands / app shell that adopts the server HTML | Full semantic HTML, headings, code blocks, links | Good: real `<title>`, meta, canonical, JSON-LD from Blade | None (PHP only) | Best |
| Plus markdown alternates (`/page.md`, `Accept: text/markdown`, `<link rel="alternate" type="text/markdown">`, `llms.txt`) | Clean, token-efficient source | Neutral | None | Strongly recommended (see section 5) |

Recommended rendering contract for v3 (design sketch):

```blade
{{-- larecipe::page --}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
  <title>{{ $page->title }} | {{ config('larecipe.name') }}</title>
  <meta name="description" content="{{ $page->description }}">
  <link rel="canonical" href="{{ $page->url }}">
  <link rel="alternate" type="text/markdown" href="{{ $page->url }}.md">
  {{ $larecipeVite }}   {{-- see 6.3 --}}
</head>
<body>
  <nav data-island="sidebar" data-props='@json($sidebar)'>{!! $sidebarHtml !!}</nav>
  <main id="content">{!! $page->html !!}</main>   {{-- full article rendered by CommonMark in PHP --}}
  <div data-island="search"></div>
  <div data-island="ask-ai"></div>
  <script type="application/json" id="larecipe-page">@json($pageProps)</script>
</body>
</html>
```

```tsx
// resources/js/app.tsx (prebuilt into dist/)
const islands = { search: Search, 'ask-ai': AskAi, sidebar: Sidebar }
document.querySelectorAll<HTMLElement>('[data-island]').forEach((el) => {
  const C = islands[el.dataset.island as keyof typeof islands]
  const props = el.dataset.props ? JSON.parse(el.dataset.props) : {}
  createRoot(el).render(<C {...props} />)   // or hydrateRoot when markup is React-identical
})
// Embedded components inside the article (e.g. <div data-component="Tabs" data-props="...">)
// are mounted the same way, so the static HTML stays readable without JS.
```

Client-side navigation between docs pages can still be added (fetch next page's JSON or HTML fragment and swap `#content`, or Inertia protocol responses) without giving up server HTML on first load. If Inertia is kept for navigation, the server can still emit the full article HTML inside the root view for the first visit and pass the same HTML as a prop, so non-JS clients get content and React takes over on boot (unverified as a documented Inertia pattern; it is a custom root view technique).

---

## 7. Markdown processing in PHP

### 7.1 league/commonmark: current state

- Installed in nashra-app: `league/commonmark 2.10.3` (2026-09-21). `composer.json` requires `php: ^7.4 || ^8.0`, `ext-mbstring`, `league/config ^1.1.1`, `psr/event-dispatcher ^1.0`. (vendor/league/commonmark/composer.json, CHANGELOG.md)
- Recent security-relevant releases (CHANGELOG.md):
  - 2.10.0 (2026-08-11): DoS fix in `AttributesExtension`; new `table_of_contents/max_placeholder_entries`; `slug_normalizer/reserved`; `default_attributes` gains `strict_callables`.
  - 2.10.2 (2026-09-21): DoS in `Table` extension (GHSA-3q6v-r5mr-hxv8) and `DisallowedRawHtml` bypass (GHSA-97jj-33gv-5xf9).
  - 2.10.3 (2026-09-21): "Fixed inline raw HTML tags being escaped when the tag name or an attribute name contains an uppercase letter, such as `<svg viewBox="...">` or `<Warning>`". Directly relevant to JSX-style component tags.
  - 2.7.0: fixed CVE-2025-46734 (Attributes extension XSS, `![](){onerror=alert(1)}`); now blocks `on*` attributes and adds an `attributes.allow` allowlist. https://github.com/advisories/GHSA-3527-qv2q-pfvx
- Recommendation: require `league/commonmark: ^2.10.3`.

Bundled extensions (https://commonmark.thephpleague.com/2.x/extensions/overview/ and `vendor/league/commonmark/src/Extension/`):

| Extension class | In GFM bundle | Notes |
| - | - | - |
| `GithubFlavoredMarkdownExtension` | (bundle) | Autolink, DisallowedRawHtml, Strikethrough, Table, TaskList |
| `Attributes\AttributesExtension` | no | `{#id .class key=value}`; config `attributes.allow` (string[]) |
| `DefaultAttributes\DefaultAttributesExtension` | no | add classes per node type via config |
| `DescriptionList\DescriptionListExtension` | no | Markdown Extra `Term\n: Definition` |
| `Embed\EmbedExtension` | no | oEmbed-style embeds, start parser priority 300 |
| `ExternalLink\ExternalLinkExtension` | no | `rel`, `target` on external links |
| `Footnote\FootnoteExtension` | no | |
| `FrontMatter\FrontMatterExtension` | no | YAML via `symfony/yaml` (or ext-yaml) |
| `HeadingPermalink\HeadingPermalinkExtension` | no | anchors + ids |
| `Highlight\HighlightExtension` | no | `==mark==` -> `<mark>` (not code highlighting) |
| `Mention\MentionExtension` | no | `@user`, `#123` |
| `NormalizeHeadings\NormalizeHeadingsExtension` | no | fixes heading outline |
| `SmartPunct\SmartPunctExtension` | no | |
| `TableOfContents\TableOfContentsExtension` | no | requires HeadingPermalink |

Note: the docs overview page marks Attributes, DescriptionList, Footnotes and others with a GFM tick, but `GithubFlavoredMarkdownExtension::register()` in vendor only adds Autolink, DisallowedRawHtml, Strikethrough, Table and TaskList. Register the others explicitly.

Key config (defaults from docs and vendor schema):

```php
$config = [
    'html_input' => 'escape',          // 'strip' | 'allow' | 'escape'
    'allow_unsafe_links' => false,
    'max_nesting_level' => 100,
    'heading_permalink' => [
        'html_class' => 'heading-permalink',
        'id_prefix' => 'content',       // set '' for clean #slugs
        'apply_id_to_heading' => false, // true puts id on <hN>
        'heading_class' => '',
        'fragment_prefix' => 'content', // set ''
        'insert' => 'before',           // 'before' | 'after' | 'none'
        'min_heading_level' => 1,
        'max_heading_level' => 6,
        'title' => 'Permalink',
        'symbol' => HeadingPermalinkRenderer::DEFAULT_SYMBOL,
        'aria_hidden' => true,
    ],
    'table_of_contents' => [
        'html_class' => 'table-of-contents',
        'position' => 'top',            // 'top' | 'before-headings' | 'placeholder'
        'style' => 'bullet',            // 'bullet' | 'ordered'
        'min_heading_level' => 1,
        'max_heading_level' => 6,
        'normalize' => 'relative',      // 'relative' | 'flat' | 'as-is'
        'placeholder' => null,          // e.g. '[TOC]'
        'max_placeholder_entries' => null,
    ],
    'attributes' => ['allow' => ['id', 'class', 'target', 'rel']],
    'slug_normalizer' => ['max_length' => 255, 'unique' => 'document', 'reserved' => []], // 'reserved' since 2.10.0
];
```

Front matter retrieval (https://commonmark.thephpleague.com/2.x/extensions/front-matter/):

```php
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;

$result = $converter->convert($markdown);
if ($result instanceof RenderedContentWithFrontMatter) {
    $meta = $result->getFrontMatter();   // array from YAML
}

// Frontmatter only (fast path for building nav / search index):
$parsed = (new FrontMatterExtension())->getFrontMatterParser()->parse($markdown);
$parsed->getFrontMatter(); $parsed->getContent();
```

Do not use Laravel's `Str::markdown()` for this: it builds a `GithubFlavoredMarkdownConverter` and returns `(string)`, so front matter is lost (vendor/laravel/framework/src/Illuminate/Support/Str.php:792).

### 7.2 Recommended environment for a docs package

```php
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\MarkdownConverter;
use Phiki\Adapters\CommonMark\PhikiExtension;
use Phiki\Theme\Theme;

$env = new Environment($config);
$env->addExtension(new CommonMarkCoreExtension())
    ->addExtension(new GithubFlavoredMarkdownExtension())
    ->addExtension(new FrontMatterExtension())
    ->addExtension(new HeadingPermalinkExtension())
    ->addExtension(new AttributesExtension())
    ->addExtension(new DescriptionListExtension())
    ->addExtension(new FootnoteExtension())
    ->addExtension(new DirectiveExtension())          // custom, see 7.3
    ->addExtension(new PhikiExtension(['light' => Theme::GithubLight, 'dark' => Theme::GithubDark]));

$converter = new MarkdownConverter($env);
```

Extracting a TOC as data (for a React "On this page" rail) instead of rendering the TOC extension: parse to AST and walk it. APIs verified in vendor (`MarkdownParser::parse(string): Document`, `HtmlRenderer::renderDocument(Document)`, `HeadingPermalink::getSlug()`, `Heading::getLevel()`).

```php
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\StringContainerHelper;

$document = (new MarkdownParser($env))->parse($markdown);   // HeadingPermalink runs on DocumentParsedEvent
$toc = [];
foreach ($document->iterator() as $node) {
    if ($node instanceof Heading && $node->getLevel() >= 2 && $node->getLevel() <= 3) {
        $permalink = $node->firstChild();
        $toc[] = [
            'level' => $node->getLevel(),
            'id'    => $permalink instanceof HeadingPermalink ? $permalink->getSlug() : null,
            'text'  => StringContainerHelper::getChildText($node, [HeadingPermalink::class]),
        ];
    }
}
$html = (string) (new HtmlRenderer($env))->renderDocument($document);
```

(Verified in vendor: `Node::iterator(int $flags = 0): NodeIterator`, `StringContainerHelper::getChildText(Node $node, array $excludeTypes = []): string`, `HeadingPermalinkProcessor` listens on `DocumentParsedEvent` at priority -100, so permalinks exist right after `parse()`. With `insert => 'before'` the `HeadingPermalink` is the heading's first child; with `'after'` it is the last child.)

### 7.3 Custom block syntax: `:::note` container directive

API (vendor `src/Parser/Block/*`, https://commonmark.thephpleague.com/2.x/customization/block-parsing/):

- `BlockStartParserInterface::tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart`
- `BlockStart::none()`, `BlockStart::of(BlockContinueParserInterface ...$parsers)->at(Cursor $cursor)`, `->replaceActiveBlockParser()`, `BlockStart::abort()`
- `BlockContinueParserInterface`: `getBlock()`, `isContainer()`, `canHaveLazyContinuationLines()`, `canContain(AbstractBlock)`, `tryContinue(Cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue`, `addLine(string)`, `closeBlock()`. Base class `AbstractBlockContinueParser`.
- `BlockContinue::none()`, `BlockContinue::at(Cursor)`, `BlockContinue::finished()` (closes this block and all open children; the closing line is consumed).
- Implement `BlockContinueParserWithInlinesInterface::parseInlines()` only for leaf blocks with inline content; containers get child paragraphs parsed automatically.
- Priorities in `CommonMarkCoreExtension`: BlockQuote 70, Heading 60, FencedCode 50, HtmlBlock 40, ThematicBreak 20, List 10, IndentedCode -100. "If your custom block starts with a letter character you'll need to add your parser ... with a priority of 250 or higher."
- `HtmlElement` escapes attribute values via `Xml::escape()`; renderers can return it as `\Stringable`.

```php
<?php

namespace LaRecipe\Markdown\Directive;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class Directive extends AbstractBlock
{
    /** @param array<string,string> $attrs */
    public function __construct(
        public readonly string $name,
        public readonly ?string $title = null,
        public readonly array $attrs = [],
    ) {
        parent::__construct();
    }
}

final class DirectiveStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== ':') {
            return BlockStart::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();
        // :::name Optional title {key=value key2="v 2"}
        if (! preg_match('/^(:{3,})\s*([a-z][a-z0-9-]*)\s*([^{]*?)\s*(?:\{([^}]*)\})?\s*$/', $cursor->getRemainder(), $m)) {
            return BlockStart::none();
        }

        preg_match_all('/([a-zA-Z][\w-]*)=(?:"([^"]*)"|(\S+))/', $m[4] ?? '', $pairs, PREG_SET_ORDER);
        $attrs = [];
        foreach ($pairs as $p) {
            $attrs[$p[1]] = $p[2] !== '' ? $p[2] : ($p[3] ?? '');
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new DirectiveParser(strlen($m[1]), $m[2], $m[3] !== '' ? $m[3] : null, $attrs))->at($cursor);
    }
}

final class DirectiveParser extends AbstractBlockContinueParser
{
    private Directive $block;

    public function __construct(private int $fenceLength, string $name, ?string $title, array $attrs)
    {
        $this->block = new Directive($name, $title, $attrs);
    }

    public function getBlock(): Directive { return $this->block; }
    public function isContainer(): bool { return true; }
    public function canContain(AbstractBlock $childBlock): bool { return true; }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        // A ":::" line inside a nested fenced code block belongs to the code block.
        if ($activeBlockParser !== $this && $activeBlockParser->getBlock() instanceof FencedCode) {
            return BlockContinue::at($cursor);
        }

        if (! $cursor->isIndented() && $cursor->getNextNonSpaceCharacter() === ':') {
            $rest = ltrim($cursor->getRemainder(), " \t");
            // Closing fence needs at least as many colons as the opener (outer containers use more colons).
            if (preg_match('/^(:{3,})[ \t]*$/', $rest, $m) && strlen($m[1]) >= $this->fenceLength) {
                return BlockContinue::finished();
            }
        }

        return BlockContinue::at($cursor);
    }
}

final class DirectiveRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Directive::assertInstanceOf($node);

        $inner = $childRenderer->renderNodes($node->children());
        if ($node->title !== null) {
            $inner = (string) new HtmlElement('p', ['class' => 'directive-title'], htmlspecialchars($node->title)) . $inner;
        }

        // Server-rendered HTML that works without JS, plus a hydration hook for React.
        return new HtmlElement('div', [
            'class' => 'directive directive-' . $node->name,
            'data-component' => ucfirst($node->name),           // e.g. "Note", "Tabs"
            'data-props' => json_encode(['type' => $node->name, 'title' => $node->title] + $node->attrs, JSON_THROW_ON_ERROR),
        ], $inner);
    }
}

final class DirectiveExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new DirectiveStartParser(), 80) // before BlockQuote (70) and FencedCode (50)
            ->addRenderer(Directive::class, new DirectiveRenderer());
    }
}
```

Usage:

```markdown
:::warning Breaking change {since="3.0"}
The `docs.path` key moved to `larecipe.sources.0.path`.
:::

::::tabs
:::tab {label="Composer"}
`composer require binarytorch/larecipe`
:::
::::
```

Existing packages instead of writing your own:
- `markdown-office/commonmark-generic-directives`: implements the Generic Directives proposal, container `:::name{attrs}`, leaf `::name[label]{attrs}`, text `:name[label]{attrs}`. Requires PHP 8.2+ and league/commonmark 2.10+. Nodes `DirectiveBlock` / `DirectiveInline` with `getName()`, `getType()`, `getLabel()`, `getAttributes()` (typed `getString()`, `getInt()`, `getBool()`, `getList()`, `toArray()`). Register renderers via `$env->addRenderer(DirectiveBlock::class, new CalloutRenderer(), 10)`. Author attributes are emitted only under `data-*`. https://github.com/markdown-office/commonmark-generic-directives
- GitHub alerts (`> [!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, `[!CAUTION]`): `zenstruck/commonmark-extensions` (`Zenstruck\CommonMark\Extension\GitHub\AdmonitionExtension`, also a `TabbedExtension`), `pomodocs/commonmark-alert` (0.8, 2026-04-02, PHP >= 8.3, commonmark ^2.7). https://github.com/zenstruck/commonmark-extensions , https://packagist.org/packages/pomodocs/commonmark-alert
- `kreemer/commonmark-admonition-extension` (mkdocs `!!! note` style), `JanStanleyWatt/commonmark-container-extension` (Markdig-style containers).

Recommendation: support GitHub alerts as the primary callout syntax (renders natively on GitHub and degrades to a blockquote everywhere, including in `.md` served to agents), and `:::` directives for richer components (tabs, steps, cards).

### 7.4 JSX-style component tags (`<Callout type="warning">`)

Option A: CommonMark raw HTML passthrough (no custom parser).
- CommonMark HTML block "type 7" starts when a line is a complete open or close tag on its own (any tag name, uppercase allowed) and ends at a blank line; it cannot interrupt a paragraph. So with blank lines, inner content is still parsed as Markdown:

```markdown
<Callout type="warning">

This is **real Markdown** inside the component.

</Callout>
```

- Output: `<Callout type="warning">` + `<p>This is <strong>real Markdown</strong>...</p>` + `</Callout>`. Requires `html_input => 'allow'` (trusted authors only). The browser parses `<Callout>` as an unknown element `callout` (lowercased). 2.10.3 fixed escaping of inline tags like `<Warning>`.
- Downsides: no validation of props, raw HTML enabled globally, no AST node to transform for agents.

Option B: custom block start parser for PascalCase tags (recommended if you want MDX-like authoring).
- Register at priority > 40 (e.g. 45) so it runs before `HtmlBlockStartParser`. Match only `^<([A-Z][A-Za-z0-9]*)(\s[^>]*)?>\s*$` (opener) and `^</\1>\s*$` (closer); parse attributes with a small attribute regex; produce a `Component` container node exactly like the `Directive` above (same `tryContinue` closing logic, matching on `</Name>`). Self-closing `<Video src="..." />` becomes a leaf block (`isContainer() false`, return `BlockContinue::none()` immediately).
- Keeps `html_input => 'escape'` for everything else, gives an allowlist of component names, and gives an AST node that can be re-serialized for agents.
- JSX expression props (`items={[1,2]}`) are not supportable without a JS parser; restrict to string/boolean/JSON-string props.

There is no maintained PHP MDX parser (search 2026-10-17 found none); MDX proper requires a JS toolchain (`@mdx-js/mdx`).

### 7.5 Server-side syntax highlighting

| | Phiki | tempest/highlight | Torchlight Engine | Torchlight API (hosted) |
| - | - | - | - | - |
| Package | `phiki/phiki` | `tempest/highlight` | `torchlight/engine` | `torchlight/torchlight-commonmark`, `torchlight/torchlight-laravel` |
| Latest | v2.2.1 (2026-07-22) | 2.28.1 (2026-09-30) | v1.0.0 (2026-03-15) | n/a |
| PHP | ^8.2, ext-mbstring, psr/simple-cache ^3.0 | ^8.4 | ^8.2 (8.4+ recommended) | |
| Engine | TextMate grammars + VS Code themes (Shiki port; ~247 grammar files in `resources/grammars`) | Hand-written regex patterns; 33 languages incl. PHP, Blade, JS, TypeScript, Vue, Svelte, Bash, SQL, YAML, JSON, Diff, Dockerfile, Nginx, Terraform | Phiki + Torchlight annotation syntax | Network API, API key |
| CommonMark | `Phiki\Adapters\CommonMark\PhikiExtension($theme, $phiki, withGutter)`; replaces `FencedCode` renderer at priority 10 | `Tempest\Highlight\CommonMark\HighlightExtension(new Highlighter())`; also highlights inline code `` `{php}code` `` | `Torchlight\Engine\CommonMark\Extension('github-light')` or `['light'=>..., 'dark'=>...]` | |
| Light/dark | `['light' => Theme::GithubLight, 'dark' => Theme::GithubDark]` | CSS themes (`github-light.css`, `github-dark.css`, 40+ in `src/Themes/Css`), or `InlineTheme` | array of themes | |
| Line features | info-string meta `{2,4-8}` highlight, `{}{2,4-8}` focus (adds `focus` class on `<pre>`); transformers and decorations API | inline markers `{+add+}`, `{-del-}`, `{~blur~}`, `{_em_}`, `{*strong*}`; `withGutter(startAt: 10)` | `// [tl! highlight]`, `[tl! focus]`, `[tl! ++]`, `[tl! --]`, `[tl! collapse:start]`/`collapse:end` | same annotations |
| Laravel | Auto cache via default cache store; Blade `<x-phiki::code grammar="php" theme="github-light" gutter>`; facade `Phiki\Adapters\Laravel\Facades\Phiki::grammar()/theme()/extend()` | none | Laravel/Statamic examples | |

Sources: https://packagist.org/packages/phiki/phiki , https://phiki.dev/commonmark , https://phiki.dev/laravel , GitHub `phikiphp/phiki` `src/Phiki.php` (`codeToHtml(string $code, string|Grammar $grammar, string|array|Theme $theme): PendingHtmlOutput`; `PendingHtmlOutput::withGutter()`, `->startingLine()`, `->transformer()`, `->decoration()`, `->cache()`, `->toString()`), https://packagist.org/packages/tempest/highlight , vendor/tempest/highlight/src/CommonMark/HighlightExtension.php, https://tempestphp.com/current/packages/highlight , https://github.com/torchlight-api/engine , https://packagist.org/packages/torchlight/engine ("The Torchlight API will remain as-is for now").

```php
use Phiki\Phiki;
use Phiki\Grammar\Grammar;
use Phiki\Theme\Theme;

$html = (new Phiki)
    ->codeToHtml($code, Grammar::Php, ['light' => Theme::GithubLight, 'dark' => Theme::GithubDark])
    ->withGutter()
    ->toString();
```

Recommendation for LaRecipe v3: Phiki as default (PHP 8.2 floor fits Laravel 12, VS Code-quality grammars for every language, dual-theme output, built-in Laravel cache). Offer Torchlight Engine as an optional driver for annotation syntax (it depends on Phiki ^2 anyway). tempest/highlight forces PHP 8.4 and has limited grammars. Highlighting is the most expensive render step, so cache rendered HTML per file hash regardless of driver.

### 7.6 React components inside server-rendered Markdown

Goal: HTML complete without JS (crawlers, LLM fetchers, no-JS clients), React enhancement when JS runs.

Pattern 1: Islands via data attributes + portals (recommended)
- PHP renders each component node to semantic, fully readable HTML with `data-component` and `data-props` (JSON, escaped by `HtmlElement`). Children are already-rendered Markdown HTML.
- The page body is passed to React as an HTML string; React renders it with `dangerouslySetInnerHTML`, then mounts interactive components into placeholders with `createPortal`, or the standalone bundle scans the DOM and calls `createRoot` per placeholder.

```tsx
// resources/js/markdown-islands.tsx
import { createRoot } from 'react-dom/client';
import { Note, Warning, Tabs } from './components';

const registry: Record<string, React.ComponentType<any>> = { Note, Warning, Tabs };

export function hydrateIslands(root: ParentNode = document) {
  root.querySelectorAll<HTMLElement>('[data-component]').forEach((el) => {
    const Cmp = registry[el.dataset.component!];
    if (!Cmp || el.dataset.mounted) return;
    const props = JSON.parse(el.dataset.props ?? '{}');
    const childrenHtml = el.innerHTML;          // server-rendered Markdown children
    el.dataset.mounted = '1';
    createRoot(el).render(<Cmp {...props}><div dangerouslySetInnerHTML={{ __html: childrenHtml }} /></Cmp>);
  });
}
```

- Pros: SEO and LLM-safe, zero Node at runtime, works with or without Inertia, components are optional enhancement. Cons: component markup is duplicated (PHP fallback markup and React markup should match visually to avoid layout shift); children are opaque HTML to React.

Pattern 2: Custom elements
- Render `<larecipe-callout type="warning">...</larecipe-callout>` (names need a hyphen) and define them with `customElements.define` or wrap React components. Valid HTML, framework-agnostic, styles apply before JS. React 19 supports custom elements as first-class (property/attribute passing). Good for host apps that do not use React.

Pattern 3: JSON AST to the client
- Serialize the commonmark AST (or an mdast-like tree) to JSON and render entirely in React (Inertia prop). Full control in React, but HTML is empty without JS unless Inertia SSR (Node) runs, which is bad for LLM crawlers and non-JS clients. Only viable if you also keep a server HTML path.

Pattern 4: MDX at build time (Node)
- Compile MDX with `@mdx-js/rollup` in Vite. Gives true JSX in docs, but moves rendering out of PHP, requires a Node build in the host app, and breaks the "edit markdown, refresh" loop for PHP-only users. Not recommended for a composer package.

Agent-facing output: for `.md` / `Accept: text/markdown` responses, transform component nodes back into portable Markdown (GitHub alert blockquotes for callouts, headings + lists for tabs) instead of shipping raw `:::` or `<Callout>`. `wnx/commonmark-markdown-renderer` (v1.6.0, 2025-11-23, PHP ^8.1, commonmark ^2.0) renders a commonmark AST back to Markdown and can be given custom renderers for `Directive` nodes; it warns that alternate syntaxes may be normalized. https://packagist.org/packages/wnx/commonmark-markdown-renderer

---

## 8. Docs-as-code automation

### 8.1 Current approaches (verified unless marked)

| Approach | Trigger | Output | Notes / source |
|---|---|---|---|
| **claude-code-action** | PR events, `workflow_run`, `schedule`, `workflow_dispatch`, `repository_dispatch` (not `push`) | Commits to the PR branch, or files edited for a follow-up PR | See 4.5. The official solution "Documentation Sync on API Changes" is in https://github.com/anthropics/claude-code-action/blob/main/docs/solutions.md |
| **openai/codex-action** (`@v1`, tag v1.13) | Any workflow event | `final-message` output; can edit the workspace under `permission-profile: ":workspace"` or `sandbox: workspace-write` | Covered below this table. https://github.com/openai/codex-action |
| **google-github-actions/run-gemini-cli** (v0.1.22, 2026-04) | Any workflow event | Gemini CLI in CI | Existence and version verified via the GitHub API. Inputs not reviewed (unverified) |
| **Claude Code headless in scripts or git hooks** | Local or CI | `claude -p "..." --allowedTools ... --output-format json [--json-schema ...]`. `--bare` skips local hooks, skills, plugins, MCP and CLAUDE.md (recommended for scripts) | https://code.claude.com/docs/en/headless |
| **Claude Code Stop / PostToolUse hooks** | During the agent session in the dev's editor | `decision:block` or `additionalContext` nudges Claude to update docs before finishing | See 4.2 |
| **Codex hooks** | During a Codex session | Same Stop semantics (`decision: "block"`, exit 2) | https://learn.chatgpt.com/docs/hooks |
| **Mintlify Automations** (formerly "Workflows") | Content update, Code change (PR merged in a connected repo), Custom schedule, Integration, Webhook | Opens a PR ("Modify and wait for review") or merges directly ("Update and merge changes") | Below this table. Docs: https://www.mintlify.com/docs/guides/use-automations, https://www.mintlify.com/docs/automations/reference |
| **Promptless** | GitHub App on PRs | Docs PRs plus a summary comment on the source PR | Below this table. https://promptless.ai/docs/for-docs/connect/triggers/github-prs |
| **Dosu pattern** (blog, 2026-03-06) | `pull_request: closed` (merged) on code paths | claude-code-action opens a follow-up `docs/update-from-pr-N` PR | Below this table. https://dosu.dev/blog/how-to-catch-documentation-drift-claude-code-github-actions |
| **Swimm-style code-coupled docs** | CI check | Fails or flags when referenced code changes | Swimm's homepage now pitches an "Agentic Development Context Layer", so the current product state is unverified. The concept (docs declare the code they cover) is still the right primitive |
| Others (DocuGardener, DriftWatch, Moxie Docs) | GitHub bots | Drift comments or PRs | Found via search only. Unverified |

**openai/codex-action details**

- Inputs: `prompt`, `prompt-file`, `output-file`, `openai-api-key`, `responses-api-endpoint`, `working-directory`, `sandbox`, `permission-profile`, `codex-version`, `codex-args`, `output-schema`, `output-schema-file`, `model`, `effort`, `codex-home`, `safety-strategy` (default `drop-sudo`), `codex-user`, `allow-users`, `allow-bots`, `allow-bot-users`.
- The README's example posts a review comment from a second job.
- PR creation is left to you, e.g. with peter-evans/create-pull-request.

**Mintlify Automations details**

- Predefined automations include "Update from code changes" (default: twice-weekly schedule, PR), "Draft changelog", "Fill gaps from assistant conversations", "Improve docs from user feedback", "Translate content", "Fix broken links", "Fix SEO issues", "Fix grammar & typos", and "Enforce your style guide".
- Up to 10 context repos per automation.
- Each run that updates docs costs 250 credits.
- Configured in the dashboard, not in files.
- Requires a Pro or Enterprise plan.
- Agent customization via `AGENTS.md`. There is an API to trigger scheduled or webhook automations from CI.

**Promptless details**

- Trigger modes: `opened` (default), `first_approval`, `merge`, `updated`.
- Config is YAML: `triggers.<id>.trigger_type: github_pr`, `match[].repos`, `trigger_on`, `trigger_directories`, `suppress_source_pr_comments`.
- Skips draft PRs and changes that touch only dot-directories.

**Dosu pattern details**

- Keeps a code-to-docs mapping table in CLAUDE.md.
- Runs `git diff --name-only base..merge` first.
- Guards: merged check, bot-author filter, `author_association` in OWNER/MEMBER/COLLABORATOR, `skip-docs-check` label, concurrency group.
- Tools: `--max-turns 15 --allowedTools "Read,Edit,Write,Glob,Grep,Bash"`.

**Common lessons across these products**

1. Run a cheap, deterministic "is a docs update needed" filter before the LLM: path filters, a code-to-docs mapping, impact scoring.
2. Always default to a reviewable PR. Direct merge is only for low-risk tasks such as translations, links and typos.
3. Batch changes on a schedule to cap cost.
4. Guard against loops: bot filters, skip labels, concurrency, branch-prefix checks.
5. Feed reader signals (unanswered assistant queries, feedback) back into automations.

### 8.2 Recommended layered design for LaRecipe v3 (installed in the user's repo)

Principle: one deterministic core that every layer reuses, then progressively more autonomous (and more expensive) layers, each opt-in, each provider-agnostic.

**Layer 0: Deterministic drift core (no AI, always on)**

- Docs declare what they cover in frontmatter. Example:

  ```yaml
  sources: ["app/Billing/**", "config/billing.php", "routes/api.php#billing"]
  ```

  An optional `symbols:` list (e.g. `App\Billing\Invoice::refund`) could also be parsed with nikic/php-parser.
- `php artisan larecipe:affected --base=<sha> --head=<sha> [--uncommitted] --format=json|github` maps `git diff --name-only` to the pages whose `sources` match. It also reports pages whose sources changed after the page's last commit (`git log -1 --format=%H -- <page>`).
- `php artisan larecipe:check` covers broken internal links, missing pages in the index, invalid frontmatter, and stale pages.
- Snippet includes, e.g. `<<< @/app/Billing/Invoice.php#refund`, make code samples in docs come from real code, so they cannot drift.
- This layer is the "impact score" that Mintlify and Promptless compute with an LLM, done for free and reproducibly.

**Layer 1: Agent guidance (static, zero runtime cost)**

The installer writes or offers:

- An `AGENTS.md` section (and `CLAUDE.md` with `@AGENTS.md` when one exists).
- `.claude/skills/larecipe-docs/SKILL.md` using portable spec-only frontmatter.
- `.claude/rules/larecipe-docs.md` with `paths: ["resources/docs/**"]`.
- Boost guidelines and skills (see Q3).
- MCP tools from the docs server (Q1): `search_docs`, `read_page`, `affected_pages`.

**Layer 2: In-session nudges (local, opt-in)**

`php artisan larecipe:install --hooks` merges into `.claude/settings.json` and `.codex/hooks.json`:

- `Stop` runs `php artisan larecipe:hook stop`:
  - If `stop_hook_active` is true, exit 0 (never loop).
  - Otherwise run `affected --uncommitted`. If code changed but no mapped page was touched, return `hookSpecificOutput.additionalContext` listing the pages. Prefer this over `decision:block`, because it is labeled feedback rather than an error.
  - Respect `LARECIPE_HOOKS=off` and a per-session "explained" marker so it only nudges once.
- `SessionStart` (matcher `startup|resume|compact`): inject a compact docs map and the stale-page count, at most a few hundred tokens.
- Optional `PostToolUse` on `Edit|Write`: when an edited file matches some page's `sources`, add a one-line `additionalContext` naming the page.
- Alternatively, distribute the same hooks and skills as a Claude Code plugin from the package repo's `.claude-plugin/marketplace.json`, registered through `extraKnownMarketplaces` and `enabledPlugins`. The trade-off is fewer settings-file edits versus a dependency on plugin trust and install.

**Layer 3: Commit-time (optional, deterministic only)**

- A pre-push hook through captainhook/captainhook, Lefthook, or Husky runs `larecipe:check` plus `affected` and warns.
- Do not call LLMs from git hooks. They are slow, need API keys, give non-deterministic results, and hurt the developer experience.

**Layer 4: CI (the source of truth; opt-in via `php artisan larecipe:install --ci=claude|codex`)**

- (a) A PR check job with no AI: run `larecipe:affected` and write a Job Summary or PR comment ("3 docs pages may need updates"). It can be made a required check that the `skip-docs` label overrides.
- (b) A post-merge sync job: Pattern B in 4.5. It runs on `pull_request: closed` (merged), never on `push`, which claude-code-action rejects. The agent runs only when the affected count is above 0, edits only, and peter-evans/create-pull-request@v8 opens `docs/sync-pr-N`.
- (c) A weekly `schedule` audit of all stale pages, as one batched PR.
- Prompt portability: keep the procedure in the repo as the `/larecipe-sync-docs` skill. claude-code-action calls it via `prompt: "/larecipe-sync-docs ..."`. codex-action reads the same file through `prompt-file`, because Codex skill discovery paths are a Q5 topic.
- Guards:
  - `concurrency` group.
  - Skip `docs/sync-*` branches.
  - Keep `allowed_bots` empty.
  - Same-repo PRs only.
  - `--max-turns` cap.
  - Untrusted PR text wrapped in delimiters.
  - A GitHub App token or PAT so CI runs on the docs PR.

**Layer 5: Reader feedback loop (optional, hosted docs)**

- Log zero-result searches, MCP `search_docs` misses, and "not helpful" votes to a JSON or Markdown queue.
- The weekly CI audit consumes that queue. This mirrors Mintlify's "Fill gaps from assistant conversations" and "Improve docs from user feedback" automations.

Why this ordering: Layers 0 and 1 give value to every user with no API key and no cost. Layer 2 catches drift at the moment the code is written, when the agent still has full context and fixing it is cheapest. Layer 4 is the safety net for humans and agents that skipped Layer 2. Every layer reuses the same `affected` computation, so all layers agree on which pages are stale.
