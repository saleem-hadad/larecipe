<?php

namespace LaRecipe\Domains\Agents\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * php artisan larecipe:mcp: the local stdio MCP server, the command agent
 * configs point at. A short alias for mcp:start larecipe.
 */
#[AsCommand(name: 'larecipe:mcp', description: 'Start the LaRecipe docs MCP server over stdio, for your coding agent')]
final class McpCommand extends Command
{
    protected $signature = 'larecipe:mcp';

    protected $description = 'Start the LaRecipe docs MCP server over stdio, for your coding agent';

    public function handle(): int
    {
        return $this->call('mcp:start', ['handle' => 'larecipe']);
    }
}
