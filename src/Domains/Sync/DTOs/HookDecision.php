<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * What the pre-push check decided: let the command run, or hold it once with
 * a reason the agent can act on. An allow may carry a short note for the
 * agent (for example, that the docs index in AGENTS.md is out of date).
 */
final readonly class HookDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $context = null,
    ) {}

    public static function allow(?string $context = null): self
    {
        return new self(true, null, $context);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
