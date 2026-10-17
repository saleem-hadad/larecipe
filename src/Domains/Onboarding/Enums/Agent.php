<?php

namespace LaRecipe\Domains\Onboarding\Enums;

/**
 * The coding agents LaRecipe knows how to set up: where their CLI is called,
 * which project folders reveal them, and where each reads MCP servers.
 *
 * MCP file locations and keys follow what Laravel Boost writes for the same
 * agents (verified in Boost 2.10), so the two never disagree.
 */
enum Agent: string
{
    case Claude = 'claude';
    case Codex = 'codex';
    case Cursor = 'cursor';
    case Gemini = 'gemini';
    case Copilot = 'copilot';

    public function label(): string
    {
        return match ($this) {
            self::Claude => 'Claude Code',
            self::Codex => 'Codex',
            self::Cursor => 'Cursor',
            self::Gemini => 'Gemini CLI',
            self::Copilot => 'GitHub Copilot',
        };
    }

    /** The CLI binary on PATH. */
    public function binary(): string
    {
        return match ($this) {
            self::Cursor => 'cursor-agent',
            default => $this->value,
        };
    }

    /** @return list<string> project files or folders that show the team uses this agent */
    public function projectMarkers(): array
    {
        return match ($this) {
            self::Claude => ['.claude', 'CLAUDE.md'],
            self::Codex => ['.codex'],
            self::Cursor => ['.cursor'],
            self::Gemini => ['.gemini', 'GEMINI.md'],
            self::Copilot => ['.github/copilot-instructions.md', '.github/skills'],
        };
    }

    /** @return array{0: string, 1: string} the MCP config file and the key servers live under */
    public function mcpConfig(): array
    {
        return match ($this) {
            self::Claude => ['.mcp.json', 'mcpServers'],
            self::Codex => ['.codex/config.toml', 'mcp_servers'],
            self::Cursor => ['.cursor/mcp.json', 'mcpServers'],
            self::Gemini => ['.gemini/settings.json', 'mcpServers'],
            self::Copilot => ['.vscode/mcp.json', 'servers'],
        };
    }

    /** Agents with a pre-tool hook LaRecipe can install. The rest rely on AGENTS.md, the git hook and CI. */
    public function supportsHooks(): bool
    {
        return in_array($this, [self::Claude, self::Codex], true);
    }

    /** Agents LaRecipe can run headless for onboarding with a verified, scoped command. */
    public function runsHeadless(): bool
    {
        return in_array($this, [self::Claude, self::Codex], true);
    }

    public static function fromInput(string $value): ?self
    {
        $value = strtolower(trim($value));

        return self::tryFrom($value) ?? match ($value) {
            'claude-code', 'claude code' => self::Claude,
            'cursor-agent' => self::Cursor,
            'gemini-cli' => self::Gemini,
            'github-copilot' => self::Copilot,
            default => null,
        };
    }
}
