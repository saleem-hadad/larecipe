<?php

namespace LaRecipe\Domains\Content\Enums;

enum Actor: string
{
    case Human = 'human';
    case Agent = 'agent';
    case Migration = 'migration';
}
