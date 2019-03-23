<?php

namespace BinaryTorch\LaRecipe\Facades;

use Illuminate\Support\Facades\Facade;

class LaRecipe extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'LaRecipe';
    }
}