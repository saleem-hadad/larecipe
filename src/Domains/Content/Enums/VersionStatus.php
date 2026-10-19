<?php

namespace LaRecipe\Domains\Content\Enums;

enum VersionStatus: string
{
    case Current = 'current';
    case Maintained = 'maintained';
    case Deprecated = 'deprecated';
    case Archived = 'archived';
}
