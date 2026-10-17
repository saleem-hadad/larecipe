<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Onboarding\Services\CodexToml;
use LaRecipe\Domains\Onboarding\Services\JsonSettings;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * Registers the local `larecipe` MCP server (`php artisan larecipe:mcp`) with
 * each agent the team uses, in that agent's own project file: `.mcp.json`,
 * `.codex/config.toml`, `.cursor/mcp.json`, `.gemini/settings.json` and
 * `.vscode/mcp.json`.
 *
 * Merged, never overwritten: other servers stay, and a `larecipe` entry the
 * user already wrote is left alone. The command runs from the project root,
 * the same convention Laravel Boost uses for its own server.
 */
final class InstallMcpConfigAction
{
    public const SERVER = 'larecipe';

    public const COMMAND = 'php';

    public const ARGS = ['artisan', 'larecipe:mcp'];

    public function __construct(
        private readonly JsonSettings $json,
        private readonly CodexToml $toml,
    ) {}

    /**
     * @param  list<Agent>  $agents
     * @return list<FileChange>
     */
    public function execute(Workspace $workspace, array $agents): array
    {
        $changes = [];

        foreach ($agents as $agent) {
            [$file, $key] = $agent->mcpConfig();
            $changes[] = $agent === Agent::Codex ? $this->toml($workspace, $file) : $this->json($workspace, $file, $key);
        }

        return $changes;
    }

    private function json(Workspace $workspace, string $file, string $key): FileChange
    {
        $settings = $this->json->decode($workspace->read($file));

        if ($settings === null) {
            return $workspace->skip($file, 'Not valid JSON, so LaRecipe left it alone. Add the larecipe server yourself: php artisan larecipe:mcp');
        }

        $server = ['command' => self::COMMAND, 'args' => self::ARGS];
        $existing = $settings[$key][self::SERVER] ?? null;

        if ($existing !== null && $existing != $server) {
            return $workspace->skip($file, 'A larecipe server is already configured here; LaRecipe left it as it is.');
        }

        $settings[$key] = is_array($settings[$key] ?? null) ? $settings[$key] : [];
        $settings[$key][self::SERVER] = $server;

        return $workspace->write($file, $this->json->encode($settings));
    }

    private function toml(Workspace $workspace, string $file): FileChange
    {
        $contents = $workspace->read($file);

        if ($this->toml->hasServer($contents, self::SERVER)) {
            return $workspace->write($file, (string) $contents);
        }

        return $workspace->write($file, $this->toml->addServer($contents, self::SERVER, self::COMMAND, self::ARGS));
    }
}
