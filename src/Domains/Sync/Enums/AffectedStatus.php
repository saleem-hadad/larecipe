<?php

namespace LaRecipe\Domains\Sync\Enums;

enum AffectedStatus: string
{
    /** The documented code changed in this range and the page did not. */
    case Stale = 'stale';

    /** The page changed in the same range as the code it documents. */
    case Updated = 'updated';
}
