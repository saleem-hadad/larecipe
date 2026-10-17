<?php

namespace LaRecipe\Domains\Onboarding\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Onboarding\Actions\MakePageAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Enums\FileChangeStatus;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan larecipe:make:page billing/refunds --title="Refunds" --source="app/Billing/**"`
 */
#[AsCommand(name: 'larecipe:make:page')]
final class MakePageCommand extends Command
{
    protected $signature = 'larecipe:make:page
        {path : The page path, for example billing/refunds}
        {--title= : The page title (default: from the path)}
        {--description= : One sentence about the page}
        {--source=* : Code the page documents, for example app/Billing/** (repeatable)}
        {--set= : The doc set (default: the default set)}
        {--docs-version= : The version (default: the default version)}
        {--locale= : The locale (default: the default locale)}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Create a docs page with title, description and sources';

    public function handle(Workspace $workspace, DocsCatalog $catalog, MakePageAction $make, SyncSettings $settings): int
    {
        $set = $catalog->set(is_string($this->option('set')) ? $this->option('set') : $settings->defaultSet);

        if ($set === null) {
            $this->components->error('There is no doc set named ['.$this->option('set').'].');

            return self::INVALID;
        }

        if ($set->isDatabase() || $set->docsPath === null) {
            $this->components->error("The {$set->key} set lives in the database. Create pages with the write_page MCP tool or the admin UI, so the change is recorded as a revision.");

            return self::INVALID;
        }

        $version = is_string($this->option('docs-version')) ? $this->option('docs-version') : $set->defaultVersion;

        if (! in_array($version, $set->versions, true)) {
            $this->components->error("The {$set->key} set has no version [{$version}].");

            return self::INVALID;
        }

        $path = (string) $this->argument('path');
        $title = is_string($this->option('title')) ? $this->option('title') : ucfirst(str_replace(['-', '_'], ' ', basename($path)));

        $change = $make->execute(
            $workspace->dryRun((bool) $this->option('dry-run')),
            $set,
            $path,
            $version,
            is_string($this->option('locale')) ? $this->option('locale') : $set->defaultLocale,
            $title,
            is_string($this->option('description')) ? $this->option('description') : null,
            array_values(array_filter((array) $this->option('source'), 'is_string')),
        );

        if ($change->status === FileChangeStatus::Skipped) {
            $this->components->warn("{$change->path}: {$change->note}");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail($change->path, $change->status->value);

        if ($workspace->exists(dirname($change->path).'/navigation.json') || $workspace->exists(rtrim((string) $set->docsPath, '/').'/'.($set->versioned ? $version.'/' : '').'navigation.json')) {
            $this->components->info('This version has a navigation.json: add the page to it so readers can find it.');
        }

        return self::SUCCESS;
    }
}
