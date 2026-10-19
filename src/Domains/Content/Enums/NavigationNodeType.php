<?php

namespace LaRecipe\Domains\Content\Enums;

enum NavigationNodeType: string
{
    case Page = 'page';
    case Group = 'group';
    case Link = 'link';
}
