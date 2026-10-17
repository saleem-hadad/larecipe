<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use LaRecipe\Domains\Sync\Contracts\RemoteAuthorTools;
use LaRecipe\Domains\Sync\Exceptions\SyncRefused;
use LaRecipe\Domains\Sync\Services\SyncLedger;
use SensitiveParameter;
use Throwable;

/**
 * Connects to a production set's MCP endpoint with laravel/mcp's HTTP client
 * and a bearer token (larecipe.ai.sync.remote_token), and returns only its
 * five author tools. The token authenticates the request and never reaches
 * the model.
 */
final class McpRemoteAuthorTools implements RemoteAuthorTools
{
    public function __construct(
        #[SensitiveParameter] private readonly ?string $token,
        private readonly float $timeout = 60.0,
    ) {}

    public function tools(string $url): array
    {
        if ($this->token === null || $this->token === '') {
            throw new SyncRefused(self::missingToken());
        }

        try {
            $tools = Client::web($url)
                ->withToken($this->token)
                ->withTimeout($this->timeout)
                ->onlyTools(SyncLedger::WRITE_TOOLS)
                ->tools()
                ->values()
                ->all();
        } catch (AuthorizationRequiredException) {
            throw new SyncRefused("[{$url}] refused the token. Use a token the remote app accepts on its MCP endpoint, for a user the manageLarecipe gate allows.");
        } catch (Throwable $exception) {
            throw new SyncRefused("LaRecipe could not reach the MCP endpoint [{$url}]: {$exception->getMessage()}");
        }

        if ($tools === []) {
            throw new SyncRefused("[{$url}] offers no author tools. On the remote app, set mcp.web.author_tools to true for that database set, and use a token of a user the manageLarecipe gate allows.");
        }

        return array_values($tools);
    }

    public static function missingToken(): string
    {
        return '--remote needs a token for the remote set\'s MCP endpoint. Set LARECIPE_SYNC_TOKEN (larecipe.ai.sync.remote_token) to a token the remote app accepts, for example a Sanctum token of a user the manageLarecipe gate allows.';
    }
}
