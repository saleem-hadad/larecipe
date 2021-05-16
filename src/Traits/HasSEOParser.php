<?php

namespace BinaryTorch\LaRecipe\Traits;

use Illuminate\Support\Facades\App;
use BinaryTorch\LaRecipe\Contracts\SEOParser;

trait HasSEOParser
{
    /**
     * @param $text
     * @return null|string|string[]
     */
    protected function parseSEO($text)
    {
        return App::make(SEOParser::class)->parse($text);
    }
}
