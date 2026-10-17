<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sources\Actions\ImportBundleAction;
use RuntimeException;

/**
 * php artisan larecipe:source:import storage/docs.bundle --set=docs [--verify]
 *
 * Loads a bundle into the set's configured source, validated first.
 */
final class SourceImportCommand extends Command
{
    protected $signature = 'larecipe:source:import
        {bundle : A bundle directory, or a .tar.gz made by larecipe:source:export}
        {--set= : The doc set (default: larecipe.default)}
        {--verify : Re-read the set afterwards and compare every content hash}';

    protected $description = 'Import a bundle of canonical files into a doc set';

    public function handle(ImportBundleAction $import, MoveReportPrinter $printer): int
    {
        $set = (string) ($this->option('set') ?: $this->laravel['config']->get('larecipe.default', 'docs'));

        try {
            $report = $import->execute($set, (string) $this->argument('bundle'), (bool) $this->option('verify'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $printer->print($this->components, $report);

        if (! $report->ok()) {
            return self::FAILURE;
        }

        $this->components->info("Imported into the [{$set}] set.");

        return self::SUCCESS;
    }
}
