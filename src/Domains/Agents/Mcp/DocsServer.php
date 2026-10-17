<?php

namespace LaRecipe\Domains\Agents\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Contracts\Transport;
use LaRecipe\LaRecipe;

/**
 * The LaRecipe docs MCP server.
 *
 * One class serves both transports: the local stdio server for the owner's
 * coding agent (php artisan larecipe:mcp, every set) and one HTTP endpoint per
 * set (bound to that set). What it exposes is composed per caller by
 * ServerComposition, from tools owned by the domains that own the behavior.
 */
#[Version(LaRecipe::VERSION)]
final class DocsServer extends Server
{
    public function __construct(
        Transport $transport,
        private readonly ServerComposition $composition,
    ) {
        parent::__construct($transport);
    }

    protected function boot(): void
    {
        $this->name = $this->composition->name();
        $this->instructions = $this->composition->instructions();
        $this->tools = $this->composition->tools();
        $this->resources = $this->composition->resources();
        $this->prompts = $this->composition->prompts();
    }
}
