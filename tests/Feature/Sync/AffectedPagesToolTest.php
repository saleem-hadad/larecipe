<?php

use Laravel\Mcp\Server;
use LaRecipe\Domains\Sync\Mcp\AffectedPagesTool;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

/** A stand-in for the docs MCP server the Agents domain composes. */
class AffectedPagesTestServer extends Server
{
    protected array $tools = [AffectedPagesTool::class];
}

it('registers affected_pages with the docs MCP server', function () {
    expect(app(LaRecipe::class)->mcpTools())->toHaveKey(AffectedPagesTool::class)
        ->and(app(LaRecipe::class)->mcpTools()[AffectedPagesTool::class]['author'])->toBeFalse();
});

it('answers which pages a change affects, over MCP', function () {
    $project = TemporaryProject::create('app')->init();
    $project->commit('Initial')->branch('feature');
    $project->mount($this->app);
    $project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    AffectedPagesTestServer::tool(AffectedPagesTool::class, ['base' => 'main'])
        ->assertOk()
        ->assertName('affected_pages')
        ->assertStructuredContent(fn ($json) => $json
            ->where('summary.stale', 1)
            ->where('pages.0.path', 'billing')
            ->where('pages.0.status', 'stale')
            ->etc());

    $project->destroy();
});
