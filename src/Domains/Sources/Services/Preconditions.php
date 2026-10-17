<?php

namespace LaRecipe\Domains\Sources\Services;

/**
 * The optimistic-concurrency check every write shares: a change written
 * against one version of a subject must not silently replace a newer one.
 *
 * basedOn is the content hash the writer saw (ContentHashes::ABSENT for "did
 * not exist"); null skips the check. creating demands that nothing is there yet.
 */
final class Preconditions
{
    /** The reason the change conflicts, or null when it may be applied. */
    public function conflict(?string $basedOn, string $currentHash, bool $creating, string $subject): ?string
    {
        if ($creating && $currentHash !== ContentHashes::ABSENT) {
            return "{$subject} already exists.";
        }

        if ($basedOn !== null && $basedOn !== $currentHash) {
            return $currentHash === ContentHashes::ABSENT
                ? "{$subject} no longer exists."
                : "{$subject} changed since this change was written (expected {$basedOn}, found {$currentHash}).";
        }

        return null;
    }
}
