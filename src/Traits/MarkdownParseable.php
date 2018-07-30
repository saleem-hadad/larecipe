<?php

namespace BinaryTorch\LaRecipe\Traits;

use ParsedownExtra;

trait MarkdownParseable
{
    /**
     * @param $text
     * @return null|string|string[]
     * @throws \Exception
     */
    public function parse($text)
    {
        return (new ParsedownExtra)->text($text);
    }
}
