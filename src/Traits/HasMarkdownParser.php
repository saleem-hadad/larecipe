<?php

namespace BinaryTorch\LaRecipe\Traits;

use League\CommonMark\CommonMarkConverter;

trait HasMarkdownParser
{
    /**
     * @param $text
     * @return null|string|string[]
     * @throws \Exception
     */
    public function parse($text)
    {
        $converter = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => true,
        ]);

        return $converter->convertToHtml($text);
    }
}
