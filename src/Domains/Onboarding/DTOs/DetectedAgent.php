<?php

namespace LaRecipe\Domains\Onboarding\DTOs;

use LaRecipe\Domains\Onboarding\Enums\Agent;

/**
 * A coding agent found on this machine (its CLI on PATH) or in this project
 * (its config folder or instructions file).
 */
final readonly class DetectedAgent
{
    /** @param list<string> $markers project files or folders that revealed it */
    public function __construct(
        public Agent $agent,
        public ?string $binary = null,
        public array $markers = [],
    ) {}

    /** The CLI is installed, so LaRecipe can run it headless. */
    public function installed(): bool
    {
        return $this->binary !== null;
    }

    public function describe(): string
    {
        $where = array_filter([
            $this->binary !== null ? 'CLI on PATH' : null,
            $this->markers !== [] ? implode(', ', $this->markers) : null,
        ]);

        return $this->agent->label().' ('.implode('; ', $where).')';
    }
}
