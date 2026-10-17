<?php

namespace LaRecipe\Domains\References\Services;

use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Contracts\ReferenceResolver;
use LaRecipe\Domains\References\DTOs\Reference;
use Throwable;

/**
 * Wraps a live resolver with the snapshot: the running app always wins, and the
 * snapshot answers only when the app cannot (no database in CI, for example).
 *
 * A live "does not exist" is never overridden by the snapshot, so a stale
 * snapshot can never hide a reference that broke.
 */
final class SnapshotFallback implements ListsReferenceTargets, ReferenceResolver
{
    public function __construct(
        private readonly ReferenceResolver $live,
        private readonly SnapshotStore $snapshot,
    ) {}

    public function kind(): string
    {
        return $this->live->kind();
    }

    public function resolve(string $target, array $props = []): ?Reference
    {
        try {
            return $this->live->resolve($target, $props);
        } catch (Throwable) {
            return $this->snapshot->find($this->kind(), $target);
        }
    }

    public function fingerprint(): string
    {
        try {
            $live = $this->live->fingerprint();
        } catch (Throwable) {
            $live = 'unavailable';
        }

        return $live.':'.$this->snapshot->fingerprint();
    }

    public function targets(): array
    {
        try {
            return $this->live instanceof ListsReferenceTargets ? $this->live->targets() : [];
        } catch (Throwable) {
            return $this->snapshot->targets($this->kind());
        }
    }

    public function live(): ReferenceResolver
    {
        return $this->live;
    }
}
