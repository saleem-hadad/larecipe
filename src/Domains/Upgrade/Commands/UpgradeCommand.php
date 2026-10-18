<?php

namespace LaRecipe\Domains\Upgrade\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LaRecipe\Domains\Upgrade\Actions\ApplyUpgradeAction;
use LaRecipe\Domains\Upgrade\Actions\PlanUpgradeAction;
use LaRecipe\Domains\Upgrade\Actions\ValidateUpgradeAction;
use LaRecipe\Domains\Upgrade\Actions\WriteUpgradeReportAction;
use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\DTOs\UpgradePlan;
use LaRecipe\Domains\Upgrade\DTOs\V2Project;

/**
 * php artisan larecipe:upgrade [--apply] [--move-to=docs] [--relative-links]
 *
 * Upgrades a LaRecipe 2 app: config, navigation, content. A dry run by default,
 * so the report can be read before anything changes; --apply writes the same plan.
 */
final class UpgradeCommand extends Command
{
    // No braces in the descriptions: the signature parser reads them as its own syntax.
    protected $signature = 'larecipe:upgrade
        {--apply : Write the changes (without it, only the report is written)}
        {--move-to= : Move the docs into this folder, for example docs}
        {--relative-links : Also turn /docs/VERSION/page links into relative .md links}';

    protected $description = 'Upgrade LaRecipe 2 docs, config and navigation to LaRecipe 3';

    public function handle(PlanUpgradeAction $plan, ValidateUpgradeAction $validate, ApplyUpgradeAction $apply, WriteUpgradeReportAction $report): int
    {
        $config = $this->v2Config();

        if ($config === null) {
            return self::FAILURE;
        }

        $project = new V2Project(
            basePath: $this->laravel->basePath(),
            config: $config,
            appName: (string) ($this->laravel['config']->get('app.name') ?: 'Docs'),
            moveTo: is_string($this->option('move-to')) && $this->option('move-to') !== '' ? $this->option('move-to') : null,
            relativeLinks: (bool) $this->option('relative-links'),
        );

        try {
            $upgrade = $plan->execute($project);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $validation = $validate->execute($upgrade);
        $applied = (bool) $this->option('apply');

        if ($applied) {
            $apply->execute($upgrade);
        }

        $paths = $report->execute($upgrade, $validation, $applied);
        $this->summary($upgrade, $validation, $applied, $paths['markdown']);

        return self::SUCCESS;
    }

    /** The v2 config array, evaluated so its env() calls resolve, or null when there is nothing to upgrade. */
    private function v2Config(): ?array
    {
        $file = $this->laravel->configPath('larecipe.php');

        if (! is_file($file)) {
            $this->components->error('There is no config/larecipe.php. The upgrade reads your LaRecipe 2 config from there.');

            return null;
        }

        $config = require $file;

        if (! is_array($config)) {
            $this->components->error('config/larecipe.php does not return an array.');

            return null;
        }

        if (isset($config['sets'])) {
            $this->components->info('This app already uses the LaRecipe 3 config. There is nothing to upgrade.');

            return null;
        }

        return $config;
    }

    private function summary(UpgradePlan $upgrade, array $validation, bool $applied, string $report): void
    {
        $this->components->twoColumnDetail('Pages converted', (string) count($upgrade->pages));
        $this->components->twoColumnDetail('Files to write', (string) count(array_filter($upgrade->files, fn ($file) => $file->operation !== 'delete')));
        $this->components->twoColumnDetail('Left for you', (string) count($upgrade->entries(ReportEntry::MANUAL)));
        $this->components->twoColumnDetail('Validation', "{$validation['errors']} errors, {$validation['warnings']} warnings");

        if ($upgrade->composer !== []) {
            $this->newLine();
            $this->line('  Then swap the package:');

            foreach ($upgrade->composer as $command) {
                $this->line("    {$command}");
            }
        }

        $this->newLine();
        $this->line('  The namespace is now LaRecipe\\ (was BinaryTorch\\LaRecipe\\), and the viewLarecipe gate now receives the page and the set key.');
        $this->newLine();

        $applied
            ? $this->components->info("Upgraded. Read {$report} for what is left, or hand it to the larecipe-upgrade-v2 skill.")
            : $this->components->info("Dry run: nothing was written. Read {$report}, then run php artisan larecipe:upgrade --apply.");
    }
}
