<?php

namespace LaRecipe\Tests\Feature\Sources\Support;

use Laravel\Mcp\Server;
use LaRecipe\Domains\Sources\Mcp\DeletePageTool;
use LaRecipe\Domains\Sources\Mcp\MovePageTool;
use LaRecipe\Domains\Sources\Mcp\UpdateManifestTool;
use LaRecipe\Domains\Sources\Mcp\UpdateNavigationTool;
use LaRecipe\Domains\Sources\Mcp\WritePageTool;

/**
 * A minimal MCP server holding the author tools, so they can be tested with
 * laravel/mcp's testing API. The real server (DocsServer) is composed by the
 * Agents domain from LaRecipe::mcpTools().
 */
final class AuthorToolsServer extends Server
{
    protected array $tools = [
        WritePageTool::class,
        MovePageTool::class,
        DeletePageTool::class,
        UpdateNavigationTool::class,
        UpdateManifestTool::class,
    ];
}
