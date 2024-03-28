<?php

namespace SaleemHadad\LaRecipe\Services;

use SaleemHadad\LaRecipe\Models\SEO;
use SaleemHadad\LaRecipe\Interfaces\SEOParser as SEOParserContract;

class SEOParser implements SEOParserContract
{
    /**
     * @param $source
     * @return SEO
     */
    public function parse($source): SEO
    {
        return SEO::create([
            'author' => 'Saleem'
        ]);
    }
}
