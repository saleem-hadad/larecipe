<?php

namespace BinaryTorch\LaRecipe\Facades;

use Illuminate\Support\Facades\Facade;

class LaRecipe extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'LaRecipe';
    }
}