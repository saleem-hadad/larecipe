<?php

namespace BinaryTorch\LaRecipe\Traits;

use BinaryTorch\LaRecipe\Models\Builder;

trait Searchable
{
    /**
     * @param  $query
     * @return mixed
     */
    public static function search($query)
    {
        return app(Builder::class)->getIndex()->search($query);
    }
}
