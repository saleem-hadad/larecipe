<?php

namespace BinaryTorch\LaRecipe\Contracts;

use BinaryTorch\LaRecipe\Models\SEO;

interface SEOParser
{
    /**
     * Parse the given source to SEO object.
     *
     * @param string $source
     * @return SEO
     */
    public function parse(string $source) : SEO;
}
