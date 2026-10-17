<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * What happened to a requested change.
 */
enum WriteOutcome: string
{
    /** Live now (or in the working tree, for a repository set). */
    case Published = 'published';

    /** Saved as a draft for a person to review. */
    case Drafted = 'drafted';

    /** The content changed since the writer read it; kept as a conflict draft, or refused. */
    case Conflicted = 'conflicted';

    /** Identical to what is already there, so nothing was written. */
    case Unchanged = 'unchanged';

    /** Not written: validation errors, a missing page, a taken path. */
    case Rejected = 'rejected';
}
