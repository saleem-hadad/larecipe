<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\BuildSessionContextAction;
use LaRecipe\Domains\Sync\Actions\CheckPrePushAction;
use LaRecipe\Domains\Sync\Actions\FindAffectedPagesAction;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\DTOs\HookDecision;
use LaRecipe\Domains\Sync\DTOs\HookPayload;
use LaRecipe\Domains\Sync\Enums\AffectedFormat;
use LaRecipe\Domains\Sync\Enums\HookClient;
use LaRecipe\Domains\Sync\Services\AffectedFormatter;
use LaRecipe\Domains\Sync\Services\PushRange;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php artisan larecipe:hook {event}`: what the agent hooks and the git hook
 * run once vendor/bin/larecipe-hook has decided the framework is needed.
 *
 *   pre-push       an agent is about to push or open a pull request
 *   session-start  an agent session starts, resumes or compacts
 *   git-pre-push   a person runs git push (warn only, or --strict)
 *
 * Output follows the PreToolUse and SessionStart JSON shapes that Claude Code
 * and Codex both read. Nothing is printed when the answer is "go ahead".
 */
#[AsCommand(name: 'larecipe:hook')]
final class HookCommand extends Command
{
    protected $signature = 'larecipe:hook
        {event : pre-push, session-start or git-pre-push}
        {--client=claude : The agent calling: claude or codex}
        {--strict : For git-pre-push, block the push when pages are stale}
        {--payload= : The hook payload as JSON (default: read from stdin)}';

    protected $description = 'Run LaRecipe\'s docs check for an agent hook or the git pre-push hook';

    public function handle(
        CheckPrePushAction $check,
        BuildSessionContextAction $session,
        FindAffectedPagesAction $affected,
        PushRange $range,
        AffectedFormatter $formatter,
    ): int {
        if (getenv('LARECIPE_HOOKS') === 'off') {
            return self::SUCCESS;
        }

        $client = HookClient::fromOption($this->option('client'));

        return match ($this->argument('event')) {
            'pre-push' => $this->prePush($check, $client),
            'session-start' => $this->sessionStart($session),
            'git-pre-push' => $this->gitPrePush($affected, $range, $formatter),
            default => $this->unknown(),
        };
    }

    private function prePush(CheckPrePushAction $check, HookClient $client): int
    {
        $decision = $check->execute(HookPayload::fromJson($this->payload()));

        $output = $this->preToolUseOutput($decision, $client);

        if ($output !== null) {
            $this->output->writeln((string) json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function preToolUseOutput(HookDecision $decision, HookClient $client): ?array
    {
        if (! $decision->allowed) {
            return ['hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
                'permissionDecision' => 'deny',
                'permissionDecisionReason' => $decision->reason,
            ]];
        }

        // Codex treats PreToolUse fields it does not support as a failed hook,
        // so the extra note is only sent to Claude Code.
        if ($decision->context !== null && $client === HookClient::Claude) {
            return ['hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
                'additionalContext' => $decision->context,
            ]];
        }

        return null;
    }

    private function sessionStart(BuildSessionContextAction $session): int
    {
        $context = $session->execute();

        $this->output->writeln((string) json_encode(['hookSpecificOutput' => [
            'hookEventName' => 'SessionStart',
            'additionalContext' => $context,
        ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    /**
     * The git hook checks the commits being pushed (uncommitted work does not
     * travel with a push). It warns by default and blocks only with --strict.
     */
    private function gitPrePush(FindAffectedPagesAction $affected, PushRange $range, AffectedFormatter $formatter): int
    {
        $report = $affected->execute(new AffectedQuery(base: $range->base(), drift: false));

        if (! $report->hasStale()) {
            return self::SUCCESS;
        }

        $text = $formatter->format($report, AffectedFormat::Text);
        $this->output->writeln($text, OutputInterface::OUTPUT_RAW);

        if ($this->option('strict')) {
            $this->output->writeln('LaRecipe held this push. Update the pages above, or push with --no-verify when no docs change is needed.', OutputInterface::OUTPUT_RAW);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function unknown(): int
    {
        $this->components->error('Unknown hook event ['.$this->argument('event').']. Use pre-push, session-start or git-pre-push.');

        return self::INVALID;
    }

    /**
     * The payload from --payload or stdin. Agents write it as soon as they
     * start the hook, so a short wait is enough; a terminal or an empty pipe
     * means there is none.
     */
    private function payload(): string
    {
        $payload = $this->option('payload');

        if (is_string($payload) && $payload !== '') {
            return $payload;
        }

        if (! defined('STDIN') || stream_isatty(STDIN)) {
            return '';
        }

        $read = [STDIN];
        $write = $except = [];

        return @stream_select($read, $write, $except, 2) > 0 ? (string) stream_get_contents(STDIN) : '';
    }
}
