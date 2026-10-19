<?php

namespace LaRecipe\Domains\Sync\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaRecipe\Domains\Sync\Actions\FindAffectedPagesAction;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\Services\PushRange;

/**
 * The MCP face of `larecipe:affected`, for the local server: an agent asks
 * which docs pages its change affects without leaving the conversation.
 * Read-only; it reads git and the docs and writes nothing.
 */
#[Name('affected_pages')]
#[Title('Docs pages affected by a change')]
#[Description('List the docs pages whose `sources:` match files changed between `base` (default: the default branch) and `head`, plus uncommitted work. Each page says whether the change already updated it (status "updated") or left it stale (status "stale"). Update stale pages before you push.')]
#[IsReadOnly]
final class AffectedPagesTool extends Tool
{
    public function __construct(
        private readonly FindAffectedPagesAction $affected,
        private readonly PushRange $range,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        $base = $request->get('base');

        $report = $this->affected->execute(new AffectedQuery(
            set: is_string($request->get('set')) ? $request->get('set') : null,
            base: is_string($base) && $base !== '' ? $base : $this->range->base(),
            head: is_string($request->get('head')) && $request->get('head') !== '' ? $request->get('head') : 'HEAD',
            uncommitted: $request->get('uncommitted', true) !== false,
            drift: (bool) $request->get('drift', false),
        ));

        return Response::structured($report->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'base' => $schema->string()->description('Branch or commit to compare with. Default: the default branch.'),
            'head' => $schema->string()->description('End of the range. Default: HEAD.'),
            'uncommitted' => $schema->boolean()->description('Include staged, unstaged and untracked changes. Default: true.'),
            'set' => $schema->string()->description('Only this doc set. Default: every set.'),
            'drift' => $schema->boolean()->description('Also list pages that drifted before this change. Default: false.'),
        ];
    }
}
