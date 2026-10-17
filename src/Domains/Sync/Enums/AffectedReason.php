<?php

namespace LaRecipe\Domains\Sync\Enums;

enum AffectedReason: string
{
    /** A file matched one of the page's `sources:` entries. */
    case Sources = 'sources';

    /** The page is a translation and its source page changed. */
    case Translation = 'translation';
}
