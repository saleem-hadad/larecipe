<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\Contracts\Vcs;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SyncPlan;
use LaRecipe\Domains\Sync\Enums\SyncTarget;

/**
 * The project files a sync run may change, so the run can say exactly which
 * files it touched and `--commit` can commit those and nothing else.
 *
 * A repository set changes files in its docs folder; a database set inside a
 * repository changes its branch docs migration. A snapshot of those folders
 * before and after the run is the honest answer, whatever the agent did.
 */
final class DocsFiles
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly Vcs $vcs,
    ) {}

    /** @return list<string> project-relative folders the run writes into */
    public function folders(SyncPlan $plan): array
    {
        return match ($plan->target) {
            SyncTarget::WorkingTree => array_values(array_filter([$plan->set->docsPath])),
            SyncTarget::DocsMigration => [$plan->set->migrationsPath],
            default => [],
        };
    }

    /** @return array<string, string> project-relative file => content hash */
    public function snapshot(SyncPlan $plan): array
    {
        $snapshot = [];

        foreach ($this->folders($plan) as $folder) {
            $folder = trim($folder, '/');

            foreach ($this->workspace->files($folder) as $file) {
                $path = ($folder === '' ? '' : $folder.'/').$file;
                $snapshot[$path] = md5((string) $this->workspace->read($path));
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string> files created, changed or deleted between two snapshots
     */
    public function changed(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $file => $hash) {
            if (($before[$file] ?? null) !== $hash) {
                $changed[] = $file;
            }
        }

        foreach (array_keys(array_diff_key($before, $after)) as $file) {
            $changed[] = (string) $file;
        }

        sort($changed);

        return $changed;
    }

    /**
     * Uncommitted files already in the run's folders. `--commit` refuses to
     * start while there are any, so it never commits someone's own edits.
     *
     * @return list<string>
     */
    public function uncommitted(SyncPlan $plan): array
    {
        $folders = array_map(fn (string $folder) => trim($folder, '/'), $this->folders($plan));

        return array_values(array_filter(
            $this->vcs->changedFiles(null, 'HEAD', true),
            function (string $file) use ($folders) {
                foreach ($folders as $folder) {
                    if ($folder === '' || str_starts_with($file, $folder.'/')) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }
}
