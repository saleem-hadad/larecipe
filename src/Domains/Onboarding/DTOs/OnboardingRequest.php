<?php

namespace LaRecipe\Domains\Onboarding\DTOs;

use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Onboarding\Enums\Audience;

/**
 * One onboarding run: who the starter docs are for, which doc set they go
 * into, and which local agent writes them.
 */
final readonly class OnboardingRequest
{
    /**
     * @param  list<Audience>  $audiences
     * @param  ?string  $docsPath  the set's folder for a repository set; null for a database set
     */
    public function __construct(
        public array $audiences,
        public string $set,
        public Agent $agent,
        public ?string $binary = null,
        public ?string $docsPath = 'docs',
    ) {}

    /** The instruction every agent receives, naming the skill so it loads the presets. */
    public function prompt(): string
    {
        $audiences = implode(', ', array_map(fn (Audience $audience) => $audience->value, $this->audiences));

        return "Use the larecipe-write-docs skill to run onboarding for audiences: {$audiences} (set: {$this->set})";
    }

    public function isDatabase(): bool
    {
        return $this->docsPath === null;
    }
}
