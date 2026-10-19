<?php

namespace LaRecipe\Domains\References\Actions;

use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Services\ReferenceRegistry;
use LaRecipe\Domains\References\Services\SnapshotFallback;
use LaRecipe\Domains\References\Services\SnapshotStore;
use LaRecipe\Support\Contracts\Clock;
use Throwable;

/**
 * Writes what every resolver can see right now to the snapshot file, so the docs
 * still resolve where the app cannot (CI without a database, the standalone CLI).
 *
 * Only the live app is read: a snapshot is never built from an older snapshot.
 */
final class SnapshotReferencesAction
{
    public function __construct(
        private readonly ReferenceRegistry $registry,
        private readonly SnapshotStore $snapshot,
        private readonly Clock $clock,
    ) {}

    /** @return array{path: string, counts: array<string, int>, failed: array<string, string>} */
    public function execute(): array
    {
        $references = [];
        $counts = [];
        $failed = [];

        foreach ($this->registry->all() as $kind => $resolver) {
            $live = $resolver instanceof SnapshotFallback ? $resolver->live() : $resolver;
            $references[$kind] = [];

            if (! $live instanceof ListsReferenceTargets) {
                continue;
            }

            try {
                foreach ($live->targets() as $target) {
                    $reference = $live->resolve($target);

                    if ($reference !== null) {
                        $references[$kind][$target] = $reference;
                    }
                }
            } catch (Throwable $exception) {
                // Keep what the last snapshot knew rather than forgetting a whole kind.
                $references[$kind] = $this->snapshot->entries($kind);
                $failed[$kind] = $exception->getMessage();
            }

            $counts[$kind] = count($references[$kind]);
        }

        $this->snapshot->write($references, $this->clock->now()->format(DATE_ATOM));

        return ['path' => $this->snapshot->path(), 'counts' => $counts, 'failed' => $failed];
    }
}
