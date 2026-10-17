<?php

namespace LaRecipe\Domains\Onboarding\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Onboarding\Actions\MakeVersionAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan larecipe:make:version 3.x --from=2.x --default`
 */
#[AsCommand(name: 'larecipe:make:version')]
final class MakeVersionCommand extends Command
{
    protected $signature = 'larecipe:make:version
        {version : The new version id, for example 3.x}
        {--from= : Copy the pages of this version (default: the default version)}
        {--default : Make the new version the default}
        {--current-as= : For a set without versions yet, the version its current pages belong to}
        {--set= : The doc set (default: the default set)}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Add a docs version by copying an existing one';

    public function handle(Workspace $workspace, DocsCatalog $catalog, MakeVersionAction $make, SyncSettings $settings): int
    {
        $set = $catalog->set(is_string($this->option('set')) ? $this->option('set') : $settings->defaultSet);

        if ($set === null || $set->isDatabase() || $set->docsPath === null) {
            $this->components->error($set === null
                ? 'There is no doc set named ['.$this->option('set').'].'
                : "The {$set->key} set lives in the database. Add versions in the admin UI or with the update_manifest MCP tool.");

            return self::INVALID;
        }

        try {
            $changes = $make->execute(
                $workspace->dryRun((bool) $this->option('dry-run')),
                $set,
                (string) $this->argument('version'),
                is_string($this->option('from')) ? $this->option('from') : null,
                (bool) $this->option('default'),
                is_string($this->option('current-as')) ? $this->option('current-as') : null,
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        foreach ($changes as $change) {
            if ($change->changed()) {
                $this->components->twoColumnDetail($change->path, $change->status->value);
            }
        }

        $this->components->info('Version '.$this->argument('version').' added. Update its pages, then run php artisan larecipe:validate.');

        return self::SUCCESS;
    }
}
