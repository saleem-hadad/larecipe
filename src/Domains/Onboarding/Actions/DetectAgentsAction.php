<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\DTOs\DetectedAgent;
use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Sync\Contracts\Workspace;

/**
 * Finds the coding agents this developer uses: a CLI on PATH (`claude`,
 * `codex`, `cursor-agent`, `gemini`, `copilot`), or the agent's folder or
 * instructions file in the project. LaRecipe configures detected agents
 * only, so it never writes config for a tool nobody here uses.
 */
final class DetectAgentsAction
{
    /** @param string $searchPath the PATH to search, injected so tests control it */
    public function __construct(private readonly string $searchPath) {}

    /** @return list<DetectedAgent> in Agent order */
    public function execute(Workspace $workspace): array
    {
        $detected = [];

        foreach (Agent::cases() as $agent) {
            $binary = $this->find($agent->binary());
            $markers = array_values(array_filter($agent->projectMarkers(), fn (string $marker) => $workspace->exists($marker)));

            if ($binary !== null || $markers !== []) {
                $detected[] = new DetectedAgent($agent, $binary, $markers);
            }
        }

        return $detected;
    }

    private function find(string $binary): ?string
    {
        foreach (explode(PATH_SEPARATOR, $this->searchPath) as $directory) {
            if ($directory === '') {
                continue;
            }

            foreach (DIRECTORY_SEPARATOR === '\\' ? [$binary.'.exe', $binary.'.cmd', $binary] : [$binary] as $name) {
                $file = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;

                if (is_file($file) && is_executable($file)) {
                    return $file;
                }
            }
        }

        return null;
    }
}
