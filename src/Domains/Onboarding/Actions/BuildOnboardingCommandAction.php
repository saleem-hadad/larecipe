<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\DTOs\AgentInvocation;
use LaRecipe\Domains\Onboarding\DTOs\OnboardingRequest;
use LaRecipe\Domains\Onboarding\Enums\Agent;

/**
 * Builds the exact headless command onboarding shows the user before asking
 * to run it, scoped so the agent can write only where the docs live.
 *
 * Claude Code (verified against `claude --help`, 2.1.x): `-p` with
 * `--allowedTools` limiting writes to the docs folder (or, for a database
 * set, to the LaRecipe MCP tools); stream-json output so progress shows as
 * it happens. Codex (verified against the Codex CLI docs): `codex exec` in
 * the workspace-write sandbox, which cannot narrow writes to one folder, so
 * the note says to review the diff. Other agents get the prompt to paste.
 */
final class BuildOnboardingCommandAction
{
    public function execute(OnboardingRequest $request): AgentInvocation
    {
        return match ($request->agent) {
            Agent::Claude => $this->claude($request),
            Agent::Codex => $this->codex($request),
            default => $this->manual($request),
        };
    }

    private function claude(OnboardingRequest $request): AgentInvocation
    {
        $prompt = $request->prompt();
        $tools = $request->isDatabase()
            ? 'Read,Grep,Glob,mcp__larecipe,Bash(php artisan larecipe:*)'
            : sprintf('Read,Grep,Glob,Edit(%1$s/**),Write(%1$s/**),Bash(php artisan larecipe:*)', trim((string) $request->docsPath, '/'));

        return new AgentInvocation(
            agent: Agent::Claude,
            arguments: [$request->binary ?? 'claude', '-p', $prompt, '--allowedTools', $tools, '--output-format', 'stream-json', '--verbose'],
            display: 'claude -p "'.$prompt.'" --allowedTools "'.$tools.'" --output-format stream-json --verbose',
            prompt: $prompt,
            note: $request->isDatabase()
                ? 'Claude Code may read the code and write only through the LaRecipe MCP tools, which record a docs migration.'
                : 'Claude Code may read the code and write only inside '.trim((string) $request->docsPath, '/').'/.',
        );
    }

    private function codex(OnboardingRequest $request): AgentInvocation
    {
        // Codex mentions skills as $name; the argument is passed as is, with no shell to expand it.
        $prompt = str_replace('the larecipe-write-docs skill', 'the $larecipe-write-docs skill', $request->prompt());

        return new AgentInvocation(
            agent: Agent::Codex,
            arguments: [$request->binary ?? 'codex', 'exec', '--sandbox', 'workspace-write', $prompt],
            display: "codex exec --sandbox workspace-write '".$prompt."'",
            prompt: $prompt,
            note: $request->isDatabase()
                ? 'Codex runs in its workspace-write sandbox and needs the larecipe MCP server in .codex/config.toml (a trusted project) to write a database set. Review the diff before you commit.'
                : 'Codex runs in its workspace-write sandbox, which cannot limit writes to one folder. Review the diff before you commit.',
        );
    }

    private function manual(OnboardingRequest $request): AgentInvocation
    {
        return new AgentInvocation(
            agent: $request->agent,
            arguments: [],
            display: $request->prompt(),
            prompt: $request->prompt(),
            runnable: false,
            note: "LaRecipe does not run {$request->agent->label()} headless yet. Open it in this project and send it the prompt above.",
        );
    }
}
