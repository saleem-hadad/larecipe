<?php

namespace BinaryTorch\LaRecipe\Traits;

use BinaryTorch\LaRecipe\Indexer;

trait Searchable
{
    /**
     * @param  $query
     * @return mixed
     */
    public static function search($query)
    {
        return app(Indexer::class)->getIndex()->search($query);
    }
}
