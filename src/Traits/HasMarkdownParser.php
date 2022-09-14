<?php

namespace BinaryTorch\LaRecipe\Traits;

use Illuminate\Support\Facades\App;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

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
