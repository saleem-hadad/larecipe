<?php

namespace BinaryTorch\LaRecipe\Services;

use League\CommonMark\CommonMarkConverter;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

class ParseDownMarkdownParser implements MarkdownParser
{
    /**
     * @param string $source
     * @return string|string[]|null
     */
    public function parse(string $source)
    {
        $converter = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => true,
        ]);

        return $converter->convertToHtml($source);
    }
}
