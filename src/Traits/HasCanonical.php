<?php

namespace BinaryTorch\LaRecipe\Traits;

trait HasCanonical
{
    /**
     * @return string
     */
    protected function getCanonical(): string
    {
        // get language, versions and path

        return route('larecipe.show');
    }
}
