<?php

namespace BinaryTorch\LaRecipe\Services;

use ParsedownExtra;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

class ParseDownMarkdownParser implements MarkdownParser
{
    /**
     * Parse the given source to Markdown, using your Markdown parser of choice.
     *
     * @param string $source Markdown source contents
     * @return null|string|string[] HTML output
     */
    public function parse($source)
    {
        return (new ParsedownExtra)->text($source);
    }
}
