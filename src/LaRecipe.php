<?php

namespace LaRecipe;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Laravel\Mcp\Server\Tool;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Markdown\Components\ComponentBuilder;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Services\MarkdownEnvironmentFactory;
use LaRecipe\Domains\Markdown\Services\RenderState;
use LaRecipe\Domains\Search\Contracts\SearchDriver;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use League\CommonMark\Environment\EnvironmentBuilderInterface;

/**
 * The host app's door into LaRecipe: every public extension point lives here,
 * behind the LaRecipe facade. Each method delegates to the domain that owns it.
 */
final class LaRecipe
{
    public const VERSION = '3.0.2';

    /** Stands in for the request's CSRF token inside the field csrfField() emits. */
    public const CSRF_PLACEHOLDER = '__LARECIPE_CSRF_TOKEN__';

    /**
     * The exact markup csrfField() emits. Only this whole field is filled in
     * when a page is sent, never the placeholder on its own, so author content
     * that names the placeholder (an image URL) never receives the token.
     */
    public const CSRF_FIELD = '<input type="hidden" name="_token" value="'.self::CSRF_PLACEHOLDER.'">';

    /** @var list<Closure> */
    private array $routes = [];

    /** @var list<Closure> */
    private array $globalRoutes = [];

    /** @var list<Closure> */
    private array $head = [];

    /** @var array<class-string, array{author: bool}> */
    private array $mcpTools = [];

    /** @var array<string, Closure> */
    private array $searchDrivers = [];

    private ?Closure $userResolver = null;

    private ?string $cspNonce = null;

    public function __construct(private readonly Container $app) {}

    /**
     * Define a component the docs may use, with its props, its server fallback
     * (works without JavaScript) and its Markdown form (what agents read).
     */
    public function component(string $name): ComponentBuilder
    {
        return $this->app->make(ComponentRegistry::class)->define($name, 'host');
    }

    /** @param Closure(EnvironmentBuilderInterface, RenderState): void $extender */
    public function extendMarkdown(Closure $extender): void
    {
        $this->app->make(MarkdownEnvironmentFactory::class)->extend($extender);
    }

    /** @param Closure(Container, SetConfig): ContentSource $factory */
    public function extendSources(string $driver, Closure $factory): void
    {
        $this->app->make(SourceManager::class)->extend($driver, $factory);
    }

    /** @param Closure(Container, array): SearchDriver $factory */
    public function extendSearch(string $driver, Closure $factory): void
    {
        $this->searchDrivers[$driver] = $factory;
    }

    /** @return array<string, Closure> */
    public function searchDrivers(): array
    {
        return $this->searchDrivers;
    }

    /**
     * Choose which fields of the signed-in user reach the browser (for custom
     * components). Nothing is shared unless the host opts in.
     *
     * @param  Closure(object): array  $resolver
     */
    public function shareUser(Closure $resolver): void
    {
        $this->userResolver = $resolver;
    }

    public function sharedUser(?object $user): ?array
    {
        return $user !== null && $this->userResolver !== null ? ($this->userResolver)($user) : null;
    }

    /** @param Closure(DocSet): mixed $callback returns HTML, a View or Htmlable for the <head> */
    public function head(Closure $callback): void
    {
        $this->head[] = $callback;
    }

    /** @return list<Closure> */
    public function headCallbacks(): array
    {
        return $this->head;
    }

    /**
     * A hidden CSRF input for component fallbacks. Rendered pages are cached and
     * shared, so the token is filled in when each response is sent.
     */
    public function csrfField(): HtmlString
    {
        return new HtmlString(self::CSRF_FIELD);
    }

    public function useCspNonce(?string $nonce): void
    {
        $this->cspNonce = $nonce;
    }

    public function cspNonce(): ?string
    {
        return $this->cspNonce;
    }

    public function set(?string $key = null): DocSet
    {
        $registry = $this->app->make(SetRegistry::class);

        return $key === null ? $registry->default() : $registry->get($key);
    }

    /**
     * Add routes inside every doc set's route group, before the page catch-all.
     * Used by LaRecipe's own domains (llms.txt, MCP, search, admin) and by hosts.
     *
     * @param  Closure(Router, SetConfig): void  $callback
     */
    public function routes(Closure $callback): void
    {
        $this->routes[] = $callback;
    }

    /** @return list<Closure> */
    public function routeCallbacks(): array
    {
        return $this->routes;
    }

    /** @param Closure(Router): void $callback routes outside any set */
    public function globalRoutes(Closure $callback): void
    {
        $this->globalRoutes[] = $callback;
    }

    /** @return list<Closure> */
    public function globalRouteCallbacks(): array
    {
        return $this->globalRoutes;
    }

    /**
     * Register an MCP tool class with the docs server. Author tools (writes) are
     * exposed on the local server, and on the web only for database sets with
     * mcp.web.author_tools enabled. Local tools (they read the developer's
     * working tree) are exposed on the local server only.
     *
     * @param  class-string<Tool>  $tool
     */
    public function mcpTool(string $tool, bool $author = false, bool $local = false): void
    {
        $this->mcpTools[$tool] = ['author' => $author, 'local' => $local];
    }

    /** @return array<class-string, array{author: bool, local: bool}> */
    public function mcpTools(): array
    {
        return $this->mcpTools;
    }
}
