<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Onboarding\Services\JsonSettings;
use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * Adds LaRecipe's pre-push check and SessionStart docs map to the agents that
 * support pre-tool hooks: Claude Code (`.claude/settings.json`) and Codex
 * (`.codex/hooks.json`). Both read the same hook shape.
 *
 * Merged into the existing settings, never overwritten, and skipped when a
 * LaRecipe hook is already there. Claude Code gives hooks the project folder
 * as $CLAUDE_PROJECT_DIR; Codex gives none, so its command finds the app
 * from the repository root.
 */
final class InstallAgentHooksAction
{
    public function __construct(
        private readonly JsonSettings $json,
        private readonly Vcs $vcs,
    ) {}

    /**
     * @param  list<Agent>  $agents
     * @return list<FileChange>
     */
    public function execute(Workspace $workspace, array $agents): array
    {
        $changes = [];

        if (in_array(Agent::Claude, $agents, true)) {
            $changes[] = $this->install($workspace, '.claude/settings.json', '"$CLAUDE_PROJECT_DIR"/vendor/bin/larecipe-hook', '');
        }

        if (in_array(Agent::Codex, $agents, true)) {
            $prefix = $this->vcs->available() ? $this->vcs->projectPrefix() : '';
            $changes[] = $this->install($workspace, '.codex/hooks.json', '"$(git rev-parse --show-toplevel)/'.$prefix.'vendor/bin/larecipe-hook"', ' --client=codex');
        }

        return $changes;
    }

    private function install(Workspace $workspace, string $file, string $binary, string $client): FileChange
    {
        $settings = $this->json->decode($workspace->read($file));

        if ($settings === null) {
            return $workspace->skip($file, 'Not valid JSON, so LaRecipe left it alone.');
        }

        $settings = $this->json->addHook($settings, 'PreToolUse', [
            'matcher' => 'Bash',
            'hooks' => [['type' => 'command', 'command' => "{$binary} pre-push{$client}", 'timeout' => 60]],
        ], 'larecipe-hook');

        $settings = $this->json->addHook($settings, 'SessionStart', [
            'matcher' => 'startup|resume|compact',
            'hooks' => [['type' => 'command', 'command' => "{$binary} session-start{$client}", 'timeout' => 30]],
        ], 'larecipe-hook');

        return $workspace->write($file, $this->json->encode($settings));
    }
}
