<?php

namespace LaRecipe\Domains\References\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\References\Actions\SnapshotReferencesAction;

/**
 * php artisan larecipe:references:snapshot
 *
 * Writes every route, command, allowlisted model and event the app can describe
 * to bootstrap/cache/larecipe/references.json. Resolvers fall back to it when
 * the app cannot answer, typically CI without a database.
 */
final class SnapshotCommand extends Command
{
    protected $signature = 'larecipe:references:snapshot';

    protected $description = 'Snapshot what live references can read, for CI without a database';

    public function handle(SnapshotReferencesAction $snapshot): int
    {
        $result = $snapshot->execute();

        foreach ($result['counts'] as $kind => $count) {
            $this->components->twoColumnDetail($kind, (string) $count);
        }

        foreach ($result['failed'] as $kind => $reason) {
            $this->components->warn("Could not read {$kind} references, kept the previous snapshot for them: {$reason}");
        }

        $this->components->info('Wrote '.$result['path']);

        return self::SUCCESS;
    }
}
