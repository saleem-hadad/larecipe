<?php

namespace LaRecipe\Domains\Sync\Enums;

enum FileChangeStatus: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Skipped = 'skipped';
    case Deleted = 'deleted';
}
