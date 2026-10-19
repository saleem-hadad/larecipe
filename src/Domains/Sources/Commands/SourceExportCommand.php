<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sources\Actions\ExportSourceAction;
use RuntimeException;

/**
 * php artisan larecipe:source:export --set=docs --to=storage/docs.bundle
 *
 * Writes the set as canonical files, to a directory or a .tar.gz.
 */
final class SourceExportCommand extends Command
{
    protected $signature = 'larecipe:source:export
        {--set= : The doc set (default: larecipe.default)}
        {--to= : A new directory, or a file ending in .tar.gz}';

    protected $description = 'Export a doc set as a bundle of canonical files (Markdown, larecipe.json, navigation.json, assets)';

    public function handle(ExportSourceAction $export, MoveReportPrinter $printer): int
    {
        $set = (string) ($this->option('set') ?: $this->laravel['config']->get('larecipe.default', 'docs'));
        $to = (string) $this->option('to');

        if ($to === '') {
            $this->components->error('Pass --to: a new directory, or a file ending in .tar.gz.');

            return self::FAILURE;
        }

        try {
            $report = $export->execute($set, $to);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $printer->print($this->components, $report);
        $this->components->info("Exported the [{$set}] set to [{$to}].");

        return self::SUCCESS;
    }
}
