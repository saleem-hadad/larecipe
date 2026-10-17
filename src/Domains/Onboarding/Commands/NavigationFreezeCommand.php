<?php

namespace LaRecipe\Domains\Onboarding\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Onboarding\Actions\FreezeNavigationAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Enums\FileChangeStatus;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan larecipe:navigation:freeze`: writes the navigation LaRecipe
 * derives from folders and frontmatter to navigation.json.
 */
#[AsCommand(name: 'larecipe:navigation:freeze')]
final class NavigationFreezeCommand extends Command
{
    protected $signature = 'larecipe:navigation:freeze
        {--set= : The doc set (default: the default set)}
        {--docs-version= : The version (default: the default version)}
        {--locale= : The locale (default: the default locale)}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Write the derived navigation to navigation.json';

    public function handle(Workspace $workspace, DocsCatalog $catalog, FreezeNavigationAction $freeze, SyncSettings $settings): int
    {
        $set = $catalog->set(is_string($this->option('set')) ? $this->option('set') : $settings->defaultSet);

        if ($set === null || $set->isDatabase() || $set->docsPath === null) {
            $this->components->error($set === null
                ? 'There is no doc set named ['.$this->option('set').'].'
                : "The {$set->key} set lives in the database. Change its navigation in the admin UI or with the update_navigation MCP tool.");

            return self::INVALID;
        }

        $change = $freeze->execute(
            $workspace->dryRun((bool) $this->option('dry-run')),
            $set,
            is_string($this->option('docs-version')) ? $this->option('docs-version') : $set->defaultVersion,
            is_string($this->option('locale')) ? $this->option('locale') : $set->defaultLocale,
        );

        if ($change->status === FileChangeStatus::Skipped) {
            $this->components->warn("{$change->path}: {$change->note}");

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail($change->path, $change->status->value);

        return self::SUCCESS;
    }
}
