<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * What a revision changes: one of the three canonical files of a doc set.
 */
enum SubjectType: string
{
    case Page = 'page';
    case Navigation = 'navigation';
    case Manifest = 'manifest';
}
