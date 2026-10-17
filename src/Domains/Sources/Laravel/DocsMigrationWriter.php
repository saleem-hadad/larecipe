<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Contracts\DocsMigrationRepository;
use LaRecipe\Domains\Sources\DTOs\AppliedOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Services\DocsMigrationOperations;
use LaRecipe\Domains\Sources\Services\DocsMigrationRenderer;
use LaRecipe\Support\Contracts\Clock;
use LaRecipe\Support\Paths;

/**
 * Writes the branch's docs migration as a session changes a database set.
 *
 * The local MCP author tools apply each change to the developer's database
 * for preview, then hand it here. There is one file per branch
 * (database/migrations/docs/{timestamp}_docs_{branch}.php), rewritten as the
 * session continues, so the pull request carries one readable file. The file
 * is logged in Laravel's migrations table as already run, so a local
 * `php artisan migrate` does not apply it twice, and its bookkeeping records
 * the local revisions, so a local `migrate:rollback` undoes the session.
 */
final class DocsMigrationWriter
{
    public function __construct(
        private readonly string $path,
        private readonly string $defaultSet,
        private readonly GitBranch $branch,
        private readonly DocsMigrationReader $reader,
        private readonly DocsMigrationRenderer $renderer,
        private readonly DocsMigrationOperations $operations,
        private readonly DocsMigrationRepository $ledger,
        private readonly MigrationRepositoryInterface $migrations,
        private readonly Clock $clock,
        private readonly ?string $stub = null,
    ) {}

    /**
     * Add one change, already applied locally, to the branch's docs migration.
     *
     * @return string|null the migration file, or null when the session's changes cancelled out
     */
    public function record(string $set, DocsMigrationOperation $operation, WriteResult $result, Provenance $author, ?string $summary = null): ?string
    {
        $name = $this->nameFor($set);
        $file = $this->path.'/'.$name.'.php';

        $plan = is_file($file)
            ? $this->reader->read($file)
            : new DocsMigrationPlan($name, $set, new Provenance(Actor::Migration, $author->name, null));

        $plan = $plan
            ->withOperations($this->operations->merge($plan->operations, $operation))
            ->withSummary($this->summary($plan->summary, $summary));

        if ($plan->operations === []) {
            $this->discard($name, $file);

            return null;
        }

        if (! is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }

        file_put_contents($file, $this->renderer->render($this->stub(), $plan));

        $this->ledger->append($name, $set, [AppliedOperation::fromResult($operation, $result)]);
        $this->markAsRun($name);

        return $file;
    }

    /** The branch's migration name: the existing file's, or a new timestamped one. */
    public function nameFor(string $set): string
    {
        $suffix = 'docs_'.($set === $this->defaultSet ? '' : $this->slug($set).'_').$this->slug($this->branch->current() ?? 'local');

        foreach (glob($this->path.'/*_'.$suffix.'.php') ?: [] as $existing) {
            $name = pathinfo($existing, PATHINFO_FILENAME);

            if (substr($name, 18) === $suffix) {
                return $name;
            }
        }

        return $this->clock->now()->format('Y_m_d_His').'_'.$suffix;
    }

    private function markAsRun(string $name): void
    {
        if (! $this->migrations->repositoryExists()) {
            $this->migrations->createRepository();
        }

        if (! in_array($name, $this->migrations->getRan(), true)) {
            $this->migrations->log($name, $this->migrations->getNextBatchNumber());
        }
    }

    /** Create then delete in one session: no file, nothing to review. */
    private function discard(string $name, string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }

        if ($this->migrations->repositoryExists()) {
            $this->migrations->delete((object) ['migration' => $name]);
        }

        $this->ledger->forget($name);
    }

    private function summary(?string $current, ?string $next): ?string
    {
        $next = $next !== null ? trim($next) : '';

        if ($current === null || $current === '') {
            return $next !== '' ? $next : null;
        }

        if ($next === '' || str_contains($current, $next)) {
            return $current;
        }

        return mb_substr($current.'; '.$next, 0, 240);
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_') ?: 'local';
    }

    private function stub(): string
    {
        return (string) file_get_contents($this->stub !== null && is_file($this->stub) ? $this->stub : Paths::stubs('docs-migration.stub'));
    }
}
