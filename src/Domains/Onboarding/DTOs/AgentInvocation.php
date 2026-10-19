<?php

namespace LaRecipe\Domains\Onboarding\DTOs;

use LaRecipe\Domains\Onboarding\Enums\Agent;

/**
 * The exact command onboarding shows the user and, with their consent, runs.
 *
 * The display string and the argument list describe the same command: what
 * the user reads is what runs. For agents LaRecipe cannot run headless with a
 * verified, scoped command, $runnable is false and the user gets the prompt
 * to paste into their agent instead.
 */
final readonly class AgentInvocation
{
    /** @param list<string> $arguments the process arguments, binary first */
    public function __construct(
        public Agent $agent,
        public array $arguments,
        public string $display,
        public string $prompt,
        public bool $runnable = true,
        public ?string $note = null,
    ) {}
}
