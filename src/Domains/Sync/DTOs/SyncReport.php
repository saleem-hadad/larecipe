<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * What a sync run did: every write the agent made, its own summary, whether a
 * budget stopped it, the files it changed in the project, and the commit when
 * one was asked for. A provider error ends the run but keeps what was already
 * written, so the person always sees the whole picture.
 */
final readonly class SyncReport
{
    /**
     * @param  list<PageChange>  $changes  in the order the agent made them
     * @param  list<string>  $files  project files the run created, changed or deleted
     */
    public function __construct(
        public SyncPlan $plan,
        public string $agent,
        public array $changes = [],
        public string $summary = '',
        public int $steps = 0,
        public bool $stepBudgetReached = false,
        public bool $pageBudgetReached = false,
        public array $files = [],
        public ?string $commit = null,
        public ?string $error = null,
    ) {}

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /**
     * The last change per subject: what each page (or the navigation, or the
     * manifest) ended up as after the agent's retries.
     *
     * @return array<string, PageChange>
     */
    public function bySubject(): array
    {
        $last = [];

        foreach ($this->changes as $change) {
            if (! $change->ok() && isset($last[$change->subject]) && $last[$change->subject]->ok()) {
                continue;
            }

            $last[$change->subject] = $change;
        }

        return $last;
    }

    /** @return list<PageChange> the changes that happened (or would have, in a dry run) */
    public function applied(): array
    {
        return array_values(array_filter($this->bySubject(), fn (PageChange $change) => $change->ok()));
    }

    /** @return list<string> the docs migration files the run wrote into */
    public function docsMigrations(): array
    {
        return array_values(array_unique(array_filter(array_map(fn (PageChange $change) => $change->docsMigration, $this->changes))));
    }

    public function drafted(): bool
    {
        foreach ($this->applied() as $change) {
            if ($change->outcome === 'drafted') {
                return true;
            }
        }

        return false;
    }

    public function with(array $changes): self
    {
        return new self(
            plan: $this->plan,
            agent: $this->agent,
            changes: $changes['changes'] ?? $this->changes,
            summary: $changes['summary'] ?? $this->summary,
            steps: $changes['steps'] ?? $this->steps,
            stepBudgetReached: $changes['stepBudgetReached'] ?? $this->stepBudgetReached,
            pageBudgetReached: $changes['pageBudgetReached'] ?? $this->pageBudgetReached,
            files: $changes['files'] ?? $this->files,
            commit: array_key_exists('commit', $changes) ? $changes['commit'] : $this->commit,
            error: array_key_exists('error', $changes) ? $changes['error'] : $this->error,
        );
    }
}
