<?php

namespace SaleemHadad\LaRecipe\Traits;

use Illuminate\Support\Facades\App;
use SaleemHadad\LaRecipe\Interfaces\MarkdownParser;

trait HasMarkdownParser
{
    /**
     * @param $text
     * @return null|string|string[]
     */
    protected function parseMarkdown($text)
    {
        try {
            return App::make(MarkdownParser::class)->parse($text);
        }catch (\Exception $exception) {
            return null;
        }
    }
}
