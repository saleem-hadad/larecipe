<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use Closure;
use LaRecipe\Domains\Onboarding\DTOs\AgentInvocation;
use LaRecipe\Domains\Onboarding\Services\AgentOutput;
use Symfony\Component\Process\Process;

/**
 * Runs the onboarding command the user just approved, in the project root,
 * printing the agent's progress as it streams. No timeout: writing a set of
 * starter docs takes minutes, and the user can stop it at any time.
 */
final class RunOnboardingAction
{
    /** @param Closure(string): void $output receives one progress line at a time */
    public function execute(AgentInvocation $invocation, string $directory, Closure $output): int
    {
        if (! $invocation->runnable || $invocation->arguments === []) {
            return 1;
        }

        $renderer = new AgentOutput;
        $process = new Process($invocation->arguments, $directory);
        $process->setTimeout(null);

        $process->run(function (string $type, string $buffer) use ($renderer, $output) {
            foreach ($renderer->feed($buffer) as $line) {
                $output($line);
            }
        });

        foreach ($renderer->flush() as $line) {
            $output($line);
        }

        return $process->getExitCode() ?? 1;
    }
}
