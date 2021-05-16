<?php

namespace BinaryTorch\LaRecipe\Services;

use BinaryTorch\LaRecipe\Models\SEO;
use \BinaryTorch\LaRecipe\Contracts\SEOParser as SEOParserContract;

class SEOParser implements SEOParserContract
{
    /**
     * @param string $source
     * @return SEO
     */
    public function parse(string $source): SEO
    {
        return SEO::create([
            'author' => 'Saleem'
        ]);
    }
}
