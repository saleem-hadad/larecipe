<?php

namespace SaleemHadad\LaRecipe\Traits;

use Illuminate\Support\Facades\App;
use SaleemHadad\LaRecipe\Interfaces\SEOParser;

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
