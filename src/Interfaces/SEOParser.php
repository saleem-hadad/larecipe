<?php

namespace SaleemHadad\LaRecipe\Interfaces;

use SaleemHadad\LaRecipe\Models\SEO;

interface SEOParser
{
    /**
     * Parse the given source to SEO object.
     *
     * @param $source
     * @return SEO
     */
    public function parse($source) : SEO;
}
