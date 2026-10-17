<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Services\DocsMigrationRenderer;
use LaRecipe\Domains\Sources\Services\PageAddresses;
use LaRecipe\Support\Paths;

/**
 * php artisan larecipe:make:docs-migration document_billing_exports --page=3.x/billing/exports
 *
 * Scaffolds a docs migration for people writing one by hand. Each --page is
 * filled in with its current content and content hash (basedOn), so the
 * author edits the Markdown in place and the change stays conflict-safe.
 */
final class MakeDocsMigrationCommand extends Command
{
    protected $signature = 'larecipe:make:docs-migration
        {name : What the migration does, for example document_billing_exports}
        {--set= : The doc set (default: larecipe.default)}
        {--page=* : Pages to include with their current content and hash, for example 3.x/billing/exports}';

    protected $description = 'Create a docs migration for a database doc set';

    public function handle(SourceResolver $sources, PageAddresses $addresses, DocsMigrationRenderer $renderer): int
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->laravel->make('larecipe.set-configs');
        $set = (string) ($this->option('set') ?: $this->laravel['config']->get('larecipe.default', 'docs'));

        if (! isset($sets[$set])) {
            $this->components->error("LaRecipe has no doc set named [{$set}].");

            return self::FAILURE;
        }

        if ($sets[$set]->driver !== 'database') {
            $this->components->warn("The [{$set}] set uses the {$sets[$set]->driver} source. Docs migrations only run for database sets; edit its Markdown files instead.");
        }

        $source = $sources->for($set);
        $manifest = $source->manifest();
        $operations = [];

        foreach ((array) $this->option('page') as $address) {
            try {
                $ref = $addresses->parse((string) $address, $set, $manifest);
            } catch (InvalidContentChange $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $page = $source->page($ref);

            if ($page === null) {
                $this->components->error("There is no page at [{$address}] in the [{$set}] set.");

                return self::FAILURE;
            }

            $operations[] = DocsMigrationOperation::updatePage($addresses->format($ref, $manifest), $page->contentHash, $page->frontmatter->toArray(), $page->body);
        }

        $slug = Str::snake((string) $this->argument('name'));
        $slug = str_starts_with($slug, 'docs_') ? $slug : 'docs_'.$slug;
        $directory = (string) $this->laravel['config']->get('larecipe.migrations.path', $this->laravel->databasePath('migrations/docs'));
        $file = $directory.'/'.date('Y_m_d_His').'_'.$slug.'.php';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $plan = new DocsMigrationPlan(pathinfo($file, PATHINFO_FILENAME), $set, new Provenance(Actor::Migration, null, null), Str::ucfirst(str_replace('_', ' ', Str::after($slug, 'docs_'))), $operations);
        $stub = $this->laravel->basePath('stubs/docs-migration.stub');

        file_put_contents($file, $renderer->render((string) file_get_contents(is_file($stub) ? $stub : Paths::stubs('docs-migration.stub')), $plan));

        $this->components->info("Docs migration [{$file}] created.");

        return self::SUCCESS;
    }
}
