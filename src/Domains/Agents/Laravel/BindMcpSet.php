<?php

namespace LaRecipe\Domains\Agents\Laravel;

use Closure;
use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Mcp\McpScope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a set's MCP endpoint to that set before the server boots, so every tool
 * on /help/mcp works on the help set and nothing else.
 */
final class BindMcpSet
{
    public function __construct(private readonly McpScope $scope) {}

    public function handle(Request $request, Closure $next, string $set): Response
    {
        $this->scope->bindWeb($set);

        return $next($request);
    }
}
