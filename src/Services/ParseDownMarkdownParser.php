<?php

namespace BinaryTorch\LaRecipe\Services;

use League\CommonMark\CommonMarkConverter;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

class ParseDownMarkdownParser implements MarkdownParser
{
    /**
     * @param $source
     * @return string|string[]|null
     */
    public function parse($source)
    {
        if(empty($source)) { return null; }

        $converter = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => true,
        ]);

        return $converter->convertToHtml($source);
    }
}
