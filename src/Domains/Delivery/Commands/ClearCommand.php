<?php

namespace LaRecipe\Domains\Delivery\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaRecipe\Domains\Delivery\Services\RenderCache;
use LaRecipe\Domains\Search\Actions\ForgetSearchIndexAction;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Forgets what larecipe:optimize compiled: the rendered pages and search
 * records it listed, then the list itself. Runs as part of
 * `php artisan optimize:clear`.
 */
#[AsCommand(name: 'larecipe:clear')]
final class ClearCommand extends Command
{
    protected $signature = 'larecipe:clear';

    protected $description = 'Forget the LaRecipe pages and search records compiled by larecipe:optimize';

    public function handle(RenderCache $cache, ForgetSearchIndexAction $forgetSearch, Filesystem $files): int
    {
        $path = $this->laravel->bootstrapPath(OptimizeCommand::MANIFEST);
        $manifest = $files->exists($path) ? (array) $files->getRequire($path) : [];

        foreach ($manifest['render_keys'] ?? [] as $key) {
            $cache->forget($key);
        }

        foreach ($manifest['sets'] ?? [] as $set => $versions) {
            foreach ($versions as $version => $locales) {
                foreach (array_keys($locales) as $locale) {
                    $forgetSearch->execute((string) $set, (string) $version, (string) $locale);
                }
            }
        }

        // Only the list: the folder also holds the hook patterns, the provenance index and the references snapshot.
        $files->delete($path);

        if ($files->isDirectory(dirname($path)) && $files->isEmptyDirectory(dirname($path))) {
            $files->deleteDirectory(dirname($path));
        }

        $this->components->info('LaRecipe compiled docs cleared.');

        return self::SUCCESS;
    }
}
