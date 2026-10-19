<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * Whether a write goes live, waits for review, or lands as a conflict.
 *
 * Repository sources ignore it: their writes land in the working tree and are
 * reviewed in the pull request. Conflict is used only by docs migrations whose
 * basedOn hash no longer matches production: the change is kept as a draft that
 * holds both versions instead of overwriting the newer one.
 */
enum WriteMode: string
{
    case Publish = 'publish';
    case Draft = 'draft';
    case Conflict = 'conflict';
}
