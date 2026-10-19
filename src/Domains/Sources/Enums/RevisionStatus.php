<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * The life of one revision in a database set.
 *
 * At most one revision per subject is Published at a time. Publishing another
 * one marks the old one Superseded; undoing it with a revert marks it Reverted.
 */
enum RevisionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Superseded = 'superseded';
    case Rejected = 'rejected';
    case Reverted = 'reverted';
    case Conflict = 'conflict';

    /** Waiting for a person in the review queue. */
    public function isPending(): bool
    {
        return $this === self::Draft || $this === self::Conflict;
    }

    /** Went live at some point, so it counts towards the set's fingerprint. */
    public function wasPublished(): bool
    {
        return in_array($this, [self::Published, self::Superseded, self::Reverted], true);
    }
}
