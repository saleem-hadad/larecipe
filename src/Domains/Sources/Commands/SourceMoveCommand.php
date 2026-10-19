<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Actions\MoveSourceAction;
use LaRecipe\Domains\Sources\DTOs\SourceMoveRequest;

/**
 * php artisan larecipe:source:move repository database --set=docs [--dry-run] [--verify]
 *
 * Copies a set between the repository and the database, losslessly, and
 * prints the one config change that switches serving to the new source. It
 * never edits config/larecipe.php itself.
 */
final class SourceMoveCommand extends Command
{
    protected $signature = 'larecipe:source:move
        {from : The source the set uses now: repository or database}
        {to : The source to move it to: repository or database}
        {--set= : The doc set (default: larecipe.default)}
        {--path= : The docs folder on the repository side, when it is not the configured one (default: docs/)}
        {--connection= : The database connection on the database side}
        {--dry-run : Show the plan and the validation, write nothing}
        {--verify : After writing, re-read the target and compare every content hash}';

    protected $description = 'Move a doc set between the repository and the database source';

    public function handle(MoveSourceAction $move, MoveReportPrinter $printer): int
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->laravel->make('larecipe.set-configs');
        $key = (string) ($this->option('set') ?: $this->laravel['config']->get('larecipe.default', 'docs'));
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');

        if (! isset($sets[$key])) {
            $this->components->error("LaRecipe has no doc set named [{$key}].");

            return self::FAILURE;
        }

        if ($from === $to || array_diff([$from, $to], ['repository', 'database']) !== []) {
            $this->components->error('Move between the two built-in sources: repository database, or database repository.');

            return self::FAILURE;
        }

        if (in_array('database', [$from, $to], true) && ! Schema::connection($this->option('connection') ?: null)->hasTable('larecipe_pages')) {
            $this->components->error('The database source tables do not exist yet. Run: php artisan vendor:publish --tag=larecipe-migrations && php artisan migrate');

            return self::FAILURE;
        }

        $set = $sets[$key];
        $toOptions = $this->sourceOptions($set, $to);
        $report = $move->execute(new SourceMoveRequest(
            $key, $from, $to,
            $this->sourceOptions($set, $from),
            $toOptions,
            (bool) $this->option('dry-run'),
            (bool) $this->option('verify'),
        ));

        $this->components->info("Moving the [{$key}] set from the {$from} source to the {$to} source.");
        $printer->print($this->components, $report);

        if ($report->validation->hasErrors()) {
            $this->components->error('Nothing was written: fix the validation errors first.');

            return self::FAILURE;
        }

        if (! $report->written) {
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if ($report->mismatches !== []) {
            return self::FAILURE;
        }

        $this->components->info('Switch serving to the new source in config/larecipe.php:');
        $this->line($this->configChange($key, $to, $toOptions));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function sourceOptions(SetConfig $set, string $driver): array
    {
        if ($driver === $set->driver) {
            return $set->source;
        }

        return $driver === 'repository'
            ? ['path' => $this->option('path') ?: $this->defaultPath($set->key)]
            : array_filter(['connection' => $this->option('connection')]);
    }

    private function defaultPath(string $set): string
    {
        return $this->laravel->basePath($set === $this->laravel['config']->get('larecipe.default', 'docs') ? 'docs' : $set);
    }

    private function configChange(string $set, string $driver, array $options): string
    {
        $base = rtrim($this->laravel->basePath(), '/').'/';
        $source = $driver === 'database'
            ? "['driver' => 'database'".(isset($options['connection']) ? ", 'connection' => '{$options['connection']}'" : '').']'
            : "['driver' => 'repository', 'path' => base_path('".(str_starts_with((string) $options['path'], $base) ? substr((string) $options['path'], strlen($base)) : $options['path'])."')]";

        return "\n    'sets' => [\n        '{$set}' => [\n            'source' => {$source},\n        ],\n    ],\n";
    }
}
