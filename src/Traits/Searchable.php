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
        $result = '';

        try {
            $result = app(Indexer::class)->getIndex()->search($query);
        } catch (\Exception $e) {
            $result = 'opps';
        }

        return $result;
    }
}
