<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Actions\ImportBundleAction;
use RuntimeException;

/**
 * php artisan larecipe:pull --set=handbook --from=storage/handbook.tar.gz
 *
 * Refreshes a local or CI database set from production, so new docs
 * migrations record production's content hashes. It reads a bundle made on
 * production by `larecipe:source:export`. Pulling straight from a production
 * MCP endpoint needs read tools that return page source (frontmatter and
 * body), which the MCP server does not offer yet, so a URL is refused with
 * that explanation.
 */
final class PullCommand extends Command
{
    protected $signature = 'larecipe:pull
        {--set= : The database doc set to refresh (default: larecipe.default)}
        {--from= : A bundle made on production with larecipe:source:export (a .tar.gz or a directory)}';

    protected $description = 'Refresh a local database doc set from a production export';

    public function handle(ImportBundleAction $import, MoveReportPrinter $printer): int
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->laravel->make('larecipe.set-configs');
        $set = (string) ($this->option('set') ?: $this->laravel['config']->get('larecipe.default', 'docs'));
        $from = (string) $this->option('from');

        if (($sets[$set]->driver ?? null) !== 'database') {
            $this->components->error("larecipe:pull refreshes database sets; [{$set}] is not one. Repository sets are already in git.");

            return self::FAILURE;
        }

        if ($from === '' || preg_match('#^https?://#i', $from)) {
            $this->components->error('Pass --from with a bundle path. On production run `php artisan larecipe:source:export --set='.$set.' --to=storage/'.$set.'.tar.gz`, copy the file here, then pull it. Pulling over MCP needs read tools that return page source, which are not available yet.');

            return self::FAILURE;
        }

        try {
            $report = $import->execute($set, $from, verify: true, by: 'larecipe:pull');
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $printer->print($this->components, $report);

        if (! $report->ok()) {
            return self::FAILURE;
        }

        $this->components->info("The [{$set}] set now matches [{$from}].");

        return self::SUCCESS;
    }
}
