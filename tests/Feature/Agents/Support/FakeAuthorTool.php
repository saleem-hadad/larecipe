<?php

namespace LaRecipe\Tests\Feature\Agents\Support;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaRecipe\Domains\Agents\Mcp\McpScope;

/**
 * Stands in for Track F's author tools (write_page and friends) to test how
 * the server composes tools registered with LaRecipe::mcpTool(author: true).
 */
#[Name('fake_write_page')]
#[IsDestructive]
final class FakeAuthorTool extends Tool
{
    public function __construct(private readonly McpScope $scope) {}

    public function handle(Request $request): Response
    {
        return Response::text('wrote to '.($this->scope->set($request->get('set')) ?? 'the default set'));
    }
}
