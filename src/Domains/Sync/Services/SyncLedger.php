<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sync\DTOs\PageChange;

/**
 * The run's record of every write the agent attempts, and the gate in front
 * of the author tools.
 *
 * It enforces what the person agreed to in the confirmation: at most
 * $maxPages pages change, and in a dry run nothing is written at all (the
 * change is recorded as planned and the agent is told so). Everything here
 * is decided in code, not in the prompt, so a confused or manipulated model
 * cannot talk its way past a budget.
 */
final class SyncLedger
{
    public const WRITE_TOOLS = ['write_page', 'move_page', 'delete_page', 'update_navigation', 'update_manifest'];

    /** @var list<PageChange> */
    private array $changes = [];

    /** @var array<string, true> pages this run has changed or tried to change */
    private array $pages = [];

    private bool $budgetReached = false;

    public function __construct(
        public readonly int $maxPages,
        public readonly bool $dryRun,
    ) {}

    public static function isWrite(string $tool): bool
    {
        return in_array($tool, self::WRITE_TOOLS, true);
    }

    /**
     * Null when the write may run. Otherwise the sentence the agent gets
     * instead of running it, with the attempt recorded.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function admit(string $tool, array $arguments): ?string
    {
        $subject = $this->subject($tool, $arguments);
        $page = $this->pageOf($tool, $arguments);

        if ($page !== null && ! isset($this->pages[$page]) && count($this->pages) >= $this->maxPages) {
            $this->budgetReached = true;
            $this->changes[] = new PageChange($tool, $subject, PageChange::REFUSED, $this->summary($arguments), 'Over the page budget.');

            return "Not done: this run may change at most {$this->maxPages} ".($this->maxPages === 1 ? 'page' : 'pages').' and that budget is used up. Change no other page. Finish now with your summary, and list ['.$subject.'] as a page that still needs an update.';
        }

        if ($page !== null) {
            $this->pages[$page] = true;
        }

        if ($this->dryRun) {
            $this->changes[] = new PageChange($tool, $subject, PageChange::PLANNED, $this->summary($arguments), null, null, $this->movedTo($tool, $arguments));

            return "Dry run: {$tool} was not executed and nothing was written. Your change to [{$subject}] is recorded for the report. Continue as if it succeeded.";
        }

        return null;
    }

    /**
     * Record what an author tool answered: its JSON result (outcome, message,
     * docs migration) or an error sentence.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function record(string $tool, array $arguments, string $result): void
    {
        $decoded = json_decode($result, true);
        $outcome = 'rejected';
        $message = trim(preg_replace('/^MCP tool error:\s*/', '', $result) ?? $result);
        $migration = null;

        if (is_array($decoded) && isset($decoded['outcome']) && is_string($decoded['outcome'])) {
            $outcome = $decoded['outcome'];
            $message = is_string($decoded['message'] ?? null) ? $decoded['message'] : null;
            $migration = is_string($decoded['docs_migration'] ?? null) ? $decoded['docs_migration'] : null;
        }

        $this->changes[] = new PageChange(
            $tool,
            $this->subject($tool, $arguments),
            $outcome,
            $this->summary($arguments),
            $message === '' ? null : $message,
            $migration,
            $this->movedTo($tool, $arguments),
        );
    }

    /** @return list<PageChange> */
    public function changes(): array
    {
        return $this->changes;
    }

    public function budgetReached(): bool
    {
        return $this->budgetReached;
    }

    /** @param array<string, mixed> $arguments */
    private function subject(string $tool, array $arguments): string
    {
        return match ($tool) {
            'update_navigation' => 'navigation',
            'update_manifest' => 'manifest',
            'move_page' => $this->path($arguments['from'] ?? null) ?? 'page',
            default => $this->path($arguments['path'] ?? null) ?? 'page',
        };
    }

    /** The page a write changes, for the page budget. Navigation and manifest edits are not pages. */
    private function pageOf(string $tool, array $arguments): ?string
    {
        return in_array($tool, ['write_page', 'move_page', 'delete_page'], true) ? $this->subject($tool, $arguments) : null;
    }

    private function movedTo(string $tool, array $arguments): ?string
    {
        return $tool === 'move_page' ? $this->path($arguments['to'] ?? null) : null;
    }

    private function summary(array $arguments): ?string
    {
        return $this->string($arguments['summary'] ?? null);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Page paths compare normalized, so "billing.md" and "billing" are one page. */
    private function path(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value === null ? null : PageRef::normalizePath($value);
    }
}
