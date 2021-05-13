<?php

namespace BinaryTorch\LaRecipe\Traits;

use Illuminate\Support\Facades\App;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

trait HasMarkdownParser
{
    /**
     * @param $text
     * @return null|string|string[]
     * @throws \Exception
     */
    public function parse($text)
    {
        return App::make(MarkdownParser::class)->parse($text);
    }
}
