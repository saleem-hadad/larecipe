<?php

namespace LaRecipe\Domains\Sync\Enums;

/**
 * The agent that called the hook. Claude Code and Codex read the same
 * PreToolUse and SessionStart output shapes today; the client is kept so a
 * future divergence stays a one-line change.
 */
enum HookClient: string
{
    case Claude = 'claude';
    case Codex = 'codex';

    public static function fromOption(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? strtolower($value) : '') ?? self::Claude;
    }
}
