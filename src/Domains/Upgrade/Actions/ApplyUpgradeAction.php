<?php

namespace LaRecipe\Domains\Upgrade\Actions;

use LaRecipe\Domains\Upgrade\DTOs\PlannedFile;
use LaRecipe\Domains\Upgrade\DTOs\UpgradePlan;

/**
 * Writes an upgrade plan to disk: new and converted files first, then moves,
 * then removals, so an interrupted run never loses a page. The v2 config is kept
 * next to the new one as config/larecipe.v2.php.bak (a name Laravel never loads).
 */
final class ApplyUpgradeAction
{
    public const CONFIG_BACKUP = 'config/larecipe.v2.php.bak';

    /** @return list<string> the paths written, copied or removed, relative to the base path */
    public function execute(UpgradePlan $plan): array
    {
        $project = $plan->project;
        $touched = [];

        if (is_file($project->path('config/larecipe.php')) && ! is_file($project->path(self::CONFIG_BACKUP))) {
            copy($project->path('config/larecipe.php'), $project->path(self::CONFIG_BACKUP));
            $touched[] = self::CONFIG_BACKUP;
        }

        $ordered = array_merge(
            array_filter($plan->files, fn (PlannedFile $file) => $file->operation !== PlannedFile::DELETE),
            array_filter($plan->files, fn (PlannedFile $file) => $file->operation === PlannedFile::DELETE),
        );

        foreach ($ordered as $file) {
            $path = $project->path($file->path);

            match ($file->operation) {
                PlannedFile::WRITE => $this->write($path, (string) $file->contents),
                PlannedFile::COPY => $this->copy($project->path((string) $file->from), $path),
                PlannedFile::DELETE => $this->delete($path, $project->path($project->docsPath())),
            };

            $touched[] = $file->path;
        }

        // After a move, the old docs folder is left only if something else still lives in it.
        $old = $project->path($project->docsPath());

        if ($project->docsPath() !== $project->targetDocsPath() && is_dir($old) && ! (new \FilesystemIterator($old))->valid()) {
            rmdir($old);
        }

        return $touched;
    }

    private function write(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
    }

    private function copy(string $from, string $to): void
    {
        if (! is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
        }

        copy($from, $to);
    }

    /** Removes a file, then any folder it leaves empty, up to (not including) the docs root. */
    private function delete(string $path, string $root): void
    {
        if (is_file($path)) {
            unlink($path);
        }

        for ($folder = dirname($path); str_starts_with($folder, $root) && $folder !== $root && is_dir($folder); $folder = dirname($folder)) {
            if ((new \FilesystemIterator($folder))->valid()) {
                break;
            }

            rmdir($folder);
        }
    }
}
