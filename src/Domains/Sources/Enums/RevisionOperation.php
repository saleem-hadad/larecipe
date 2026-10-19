<?php

namespace LaRecipe\Domains\Sources\Enums;

enum RevisionOperation: string
{
    /** New content for a page, the navigation or the manifest (creating it when needed). */
    case Write = 'write';

    /** A page moved to another path (or version, or locale). */
    case Move = 'move';

    /** A page deleted, or a navigation.json removed so navigation is derived again. */
    case Delete = 'delete';
}
