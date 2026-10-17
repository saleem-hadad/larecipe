<?php

namespace LaRecipe\Domains\Sources\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Actions\CompileProvenanceAction;
use LaRecipe\Domains\Sources\Laravel\ProvenanceIndexPath;
use LaRecipe\Domains\Sources\Services\ProvenanceIndexWriter;

/**
 * php artisan larecipe:provenance [--set=docs] [--clear]
 *
 * Compiles "who changed this page last" for repository sets into
 * bootstrap/cache/larecipe, from git when it is available. Run it at build
 * time (larecipe:optimize does), where git history exists.
 */
final class ProvenanceCommand extends Command
{
    protected $signature = 'larecipe:provenance
        {--set=* : The doc sets to compile (default: every repository set)}
        {--clear : Remove the compiled indexes instead}';

    protected $description = 'Compile page provenance (last change, author, agent, pull request) for repository doc sets';

    public function handle(CompileProvenanceAction $compile, ProvenanceIndexPath $paths, ProvenanceIndexWriter $writer): int
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->laravel->make('larecipe.set-configs');
        $keys = $this->option('set') ?: array_keys(array_filter($sets, fn (SetConfig $set) => $set->driver === 'repository'));

        foreach ($keys as $key) {
            if (! isset($sets[$key])) {
                $this->components->error("LaRecipe has no doc set named [{$key}].");

                return self::FAILURE;
            }

            if ($this->option('clear')) {
                $writer->clear($paths->for($key));
                $this->components->twoColumnDetail($key, 'cleared');

                continue;
            }

            $report = $compile->execute($key);

            $this->components->twoColumnDetail($key, $report->compiled
                ? "{$report->files} files from {$report->method}"
                : (string) $report->reason);
        }

        return self::SUCCESS;
    }
}
