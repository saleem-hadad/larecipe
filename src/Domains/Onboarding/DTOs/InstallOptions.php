<?php

namespace LaRecipe\Domains\Onboarding\DTOs;

use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Onboarding\Enums\Audience;
use LaRecipe\Domains\Sync\Enums\CiMode;
use LaRecipe\Domains\Sync\Enums\ReviewMode;

/**
 * Every choice `larecipe:install` makes, whether it came from a prompt or a
 * flag. The command gathers them; the steps act on them.
 */
final readonly class InstallOptions
{
    /**
     * @param  list<Agent>  $agents  agents to configure (MCP, hooks)
     * @param  ?string  $gitHook  null (none), "warn" or "strict"
     * @param  list<Audience>  $onboard  audiences for onboarding; empty skips it
     */
    public function __construct(
        public string $driver = 'repository',
        public string $path = 'docs',
        public array $agents = [],
        public bool $hooks = true,
        public ?string $gitHook = null,
        public ?CiMode $ci = null,
        public ReviewMode $review = ReviewMode::Required,
        public bool $claudeMd = false,
        public bool $index = true,
        public bool $composerScript = false,
        public array $onboard = [],
        public ?Agent $onboardAgent = null,
    ) {}

    public function isDatabase(): bool
    {
        return $this->driver === 'database';
    }

    public function has(Agent $agent): bool
    {
        return in_array($agent, $this->agents, true);
    }
}
