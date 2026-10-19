<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Illuminate\Contracts\Container\Container;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\McpServerTool;
use Laravel\Ai\Tools\McpTool;
use LaRecipe\Domains\Agents\Mcp\Tools\ListPagesTool;
use LaRecipe\Domains\Agents\Mcp\Tools\ReadPageTool;
use LaRecipe\Domains\Agents\Mcp\Tools\SearchDocsTool;
use LaRecipe\Domains\References\Actions\ListReferenceTargetsAction;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Mcp\DeletePageTool;
use LaRecipe\Domains\Sources\Mcp\MovePageTool;
use LaRecipe\Domains\Sources\Mcp\UpdateManifestTool;
use LaRecipe\Domains\Sources\Mcp\UpdateNavigationTool;
use LaRecipe\Domains\Sources\Mcp\WritePageTool;
use LaRecipe\Domains\Sync\Contracts\RemoteAuthorTools;
use LaRecipe\Domains\Sync\DTOs\SyncPlan;
use LaRecipe\Domains\Sync\Enums\SyncTarget;
use LaRecipe\Domains\Sync\Laravel\Tools\AppReferenceTool;
use LaRecipe\Domains\Sync\Laravel\Tools\GitDiffTool;
use LaRecipe\Domains\Sync\Laravel\Tools\GuardedTool;
use LaRecipe\Domains\Sync\Laravel\Tools\ReadCodeTool;
use LaRecipe\Domains\Sync\Laravel\Tools\ReadPageSourceTool;
use LaRecipe\Domains\Sync\Mcp\AffectedPagesTool;
use LaRecipe\Domains\Sync\Services\CodeReader;
use LaRecipe\Domains\Sync\Services\DiffReader;
use LaRecipe\Domains\Sync\Services\SyncLedger;
use LaRecipe\Domains\Validation\Mcp\ValidateDocsTool;

/**
 * The docs agent's tools for one run.
 *
 * Docs tools are the very MCP tool classes the local server and Ask AI use
 * (laravel/ai wraps them), so there is one implementation of searching,
 * reading, validating and writing. Each is guarded for the run: bound to its
 * set, defaulted to its version and locale, and, for writes, gated by the
 * ledger. A --remote run swaps the local write tools for the production
 * set's own, reached over its authenticated MCP endpoint.
 *
 * The rest are read-only and Sync's own: the page source, the code, the diff
 * and the app's public shape through the live reference resolvers.
 */
final class SyncToolbox
{
    private const LOCAL_WRITES = [WritePageTool::class, MovePageTool::class, DeletePageTool::class, UpdateNavigationTool::class, UpdateManifestTool::class];

    private const REMOTE_PREFIX = 'mcp_tools_';

    public function __construct(
        private readonly Container $app,
        private readonly RemoteAuthorTools $remote,
    ) {}

    /** @return list<Tool> */
    public function for(SyncPlan $plan, SyncLedger $ledger): array
    {
        $forced = ['set' => $plan->set->key];
        $defaults = ['version' => $plan->version, 'locale' => $plan->locale];

        $tools = [
            $this->guard(SearchDocsTool::class, $ledger, $forced, $defaults),
            $this->guard(ReadPageTool::class, $ledger, $forced, $defaults),
            $this->guard(ListPagesTool::class, $ledger, $forced, $defaults),
            $this->guard(AffectedPagesTool::class, $ledger, $forced, array_filter(['base' => $plan->base, 'head' => $plan->request->head])),
            new ReadPageSourceTool($this->app->make(GetPageAction::class), $plan->set->key, $plan->version, $plan->locale),
            new ReadCodeTool($this->app->make(CodeReader::class)),
            new GitDiffTool($this->app->make(DiffReader::class), $plan->base, $plan->request->head),
            new AppReferenceTool($this->app->make(ResolveReferenceAction::class), $this->app->make(ListReferenceTargetsAction::class)),
        ];

        if ($plan->target === SyncTarget::Remote) {
            // The remote endpoint is bound to its own set and validates every write itself.
            foreach ($this->remote->tools((string) $plan->request->remote) as $tool) {
                $inner = $this->wrap($tool);
                $tools[] = new GuardedTool($inner, $this->nameOf($inner), $ledger, [], $defaults, ['set']);
            }

            return $tools;
        }

        $tools[] = $this->guard(ValidateDocsTool::class, $ledger, $forced, ['version' => $plan->version]);

        foreach (self::LOCAL_WRITES as $class) {
            $tools[] = $this->guard($class, $ledger, $forced, $defaults);
        }

        return $tools;
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $forced
     * @param  array<string, mixed>  $defaults
     */
    private function guard(string $class, SyncLedger $ledger, array $forced, array $defaults): GuardedTool
    {
        $inner = new McpServerTool($this->app->make($class));

        return new GuardedTool($inner, $this->nameOf($inner), $ledger, $forced, $defaults);
    }

    /** laravel/mcp server tools and client tools both become laravel/ai tools. */
    private function wrap(object $tool): Tool
    {
        return match (true) {
            $tool instanceof Tool => $tool,
            McpServerTool::supports($tool) => new McpServerTool($tool),
            McpTool::supports($tool) => new McpTool($tool),
            default => throw new \InvalidArgumentException('Not an agent tool: '.$tool::class),
        };
    }

    /** The tool's MCP name ("write_page"), without the prefix laravel/ai gives remote tools. */
    private function nameOf(Tool $tool): string
    {
        $name = method_exists($tool, 'name') ? (string) $tool->name() : class_basename($tool);

        return str_starts_with($name, self::REMOTE_PREFIX) ? substr($name, strlen(self::REMOTE_PREFIX)) : $name;
    }
}
