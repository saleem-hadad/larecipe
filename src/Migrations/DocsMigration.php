<?php

namespace LaRecipe\Migrations;

use Illuminate\Container\Container;
use Illuminate\Database\Migrations\Migration;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\ApplyDocsMigrationAction;
use LaRecipe\Domains\Sources\Actions\RollbackDocsMigrationAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use ReflectionObject;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The base class of every docs migration: content changes for a database doc
 * set that ride in the pull request with the code and are applied by
 * `php artisan migrate` on deploy (ARCHITECTURE.md 5.4).
 *
 *     return new class extends DocsMigration
 *     {
 *         protected string $set = 'handbook';
 *         protected string $author = 'claude-code';
 *         protected string $ref = 'https://github.com/acme/acme/pull/482';
 *         protected string $summary = 'Document the --agents flag';
 *
 *         public function up(): void
 *         {
 *             $this->updatePage('3.x/installation', basedOn: 'sha256:...', frontmatter: [...], body: <<<'MD'
 *                 # Installation
 *                 MD);
 *         }
 *     };
 *
 * It exposes the declarative content operations and nothing else. Each one
 * goes through the Sources Actions as Actor::Migration, so validation and
 * provenance work exactly as for any other writer. `down()` is built in:
 * it restores what the migration replaced, without clobbering later edits.
 * A docs migration only runs for a set that uses the database source, and
 * not at all in tests when larecipe.migrations.skip_in_tests is on.
 */
abstract class DocsMigration extends Migration
{
    /** The doc set to change; empty means larecipe.default. */
    protected string $set = '';

    /** Who wrote the change (a person or an agent such as claude-code), shown with the page. */
    protected string $author = '';

    /** Where the change came from, such as the pull request URL; defaults to the migration's name. */
    protected string $ref = '';

    /** One line for the revision list. */
    protected string $summary = '';

    /** @var list<DocsMigrationOperation>|null operations being recorded instead of applied */
    private ?array $recording = null;

    abstract public function up(): void;

    public function down(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        $outcome = $this->container()->make(RollbackDocsMigrationAction::class)->execute($this->migrationName(), $this->provenance());

        foreach ($outcome->warnings as $warning) {
            $this->warn($warning);
        }
    }

    /**
     * Skipped (and left pending) when the set no longer uses the database
     * source, so `migrate` on a fresh database never writes into a repository
     * set's files, and in tests when skip_in_tests is on.
     */
    public function shouldRun(): bool
    {
        $config = $this->container()->make('config');

        if ($config->get('larecipe.migrations.skip_in_tests') && app()->runningUnitTests()) {
            return false;
        }

        return $config->get("larecipe.sets.{$this->setKey()}.source.driver") === 'database';
    }

    /**
     * What up() declares, without applying it. The docs migration writer reads
     * its own files this way when a session adds another change.
     */
    public function plan(): DocsMigrationPlan
    {
        $this->recording = [];

        try {
            $this->up();

            return new DocsMigrationPlan($this->migrationName(), $this->setKey(), $this->provenance(), $this->summary !== '' ? $this->summary : null, $this->recording);
        } finally {
            $this->recording = null;
        }
    }

    /** @param array<string, mixed> $frontmatter */
    protected function createPage(string $path, array $frontmatter = [], string $body = ''): void
    {
        $this->operation(DocsMigrationOperation::createPage($path, $frontmatter, $body));
    }

    /** @param array<string, mixed> $frontmatter */
    protected function updatePage(string $path, ?string $basedOn = null, array $frontmatter = [], string $body = ''): void
    {
        $this->operation(DocsMigrationOperation::updatePage($path, $basedOn, $frontmatter, $body));
    }

    protected function movePage(string $from, string $to, ?string $basedOn = null): void
    {
        $this->operation(DocsMigrationOperation::movePage($from, $to, $basedOn));
    }

    protected function deletePage(string $path, ?string $basedOn = null): void
    {
        $this->operation(DocsMigrationOperation::deletePage($path, $basedOn));
    }

    /**
     * A whole tree, or a patch (see NavigationPatcher): add, move, remove, rename.
     *
     * @param  array<string, mixed>|null  $tree
     * @param  list<array<string, mixed>>|null  $patch
     */
    protected function updateNavigation(string $version = '', ?string $basedOn = null, ?array $tree = null, ?array $patch = null, ?string $locale = null): void
    {
        $this->operation(DocsMigrationOperation::updateNavigation($version, $basedOn, $tree, $patch, $locale));
    }

    /** @param array<string, mixed> $patch a JSON merge patch for larecipe.json */
    protected function updateManifest(?string $basedOn = null, array $patch = []): void
    {
        $this->operation(DocsMigrationOperation::updateManifest($basedOn, $patch));
    }

    private function operation(DocsMigrationOperation $operation): void
    {
        if ($this->recording !== null) {
            $this->recording[] = $operation;

            return;
        }

        $outcome = $this->container()->make(ApplyDocsMigrationAction::class)->execute(new DocsMigrationPlan(
            $this->migrationName(),
            $this->setKey(),
            $this->provenance(),
            $this->summary !== '' ? $this->summary : null,
            [$operation],
        ));

        foreach ($outcome->warnings as $warning) {
            $this->warn($warning);
        }
    }

    private function provenance(): Provenance
    {
        return new Provenance(Actor::Migration, $this->author !== '' ? $this->author : null, $this->ref !== '' ? $this->ref : $this->migrationName());
    }

    private function setKey(): string
    {
        return $this->set !== '' ? $this->set : (string) $this->container()->make('config')->get('larecipe.default', 'docs');
    }

    /** The file name without .php, the same name Laravel's migrations table records. */
    private function migrationName(): string
    {
        return pathinfo((string) (new ReflectionObject($this))->getFileName(), PATHINFO_FILENAME);
    }

    /**
     * A conflict is a warning in the `migrate` output, never a failure. The
     * migrate command hands its output to the migrator, which keeps it in a
     * protected property, so it is read from there.
     */
    private function warn(string $message): void
    {
        $container = $this->container();
        $migrator = $container->bound('migrator') ? $container->make('migrator') : null;
        $output = $migrator !== null ? (fn () => $this->output)->call($migrator) : null;

        if ($output instanceof OutputInterface) {
            $output->writeln("  <fg=yellow;options=bold>WARN</> LaRecipe docs conflict: {$message}");
        }
    }

    private function container(): Container
    {
        return Container::getInstance();
    }
}
