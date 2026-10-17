<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Actions\WarmCacheAction;

/**
 * php artisan larecipe:cache:warm [--set=handbook]
 *
 * Deploy scripts call it after `php artisan migrate`, so the first readers
 * after a deploy get warm pages. Without --set it warms every database set.
 */
final class CacheWarmCommand extends Command
{
    protected $signature = 'larecipe:cache:warm {--set=* : The doc sets to warm (default: every database set)}';

    protected $description = 'Warm the cache of database doc sets (pages, navigation, manifest)';

    public function handle(WarmCacheAction $warm): int
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->laravel->make('larecipe.set-configs');
        $keys = $this->option('set') ?: array_keys(array_filter($sets, fn (SetConfig $set) => $set->driver === 'database'));

        if ($keys === []) {
            $this->components->info('No doc set uses the database source, so there is nothing to warm.');

            return self::SUCCESS;
        }

        foreach ($keys as $key) {
            if (! isset($sets[$key])) {
                $this->components->error("LaRecipe has no doc set named [{$key}].");

                return self::FAILURE;
            }

            $report = $warm->execute($key);

            $report->cached
                ? $this->components->twoColumnDetail($key, $report->pages.' '.Str::plural('page', $report->pages).", fingerprint {$report->fingerprint}")
                : $this->components->twoColumnDetail($key, 'repository set, nothing to warm');
        }

        return self::SUCCESS;
    }
}
