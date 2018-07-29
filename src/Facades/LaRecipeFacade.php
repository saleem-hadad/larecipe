<?php

namespace BinaryTorch\LaRecipe\Facades;

use Illuminate\Support\Facades\Facade;

class LaRecipeFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'LaRecipe';
    }
}