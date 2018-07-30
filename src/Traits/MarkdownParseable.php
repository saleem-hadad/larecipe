<?php

namespace BinaryTorch\LaRecipe\Traits;

use ParsedownExtra;

trait MarkdownParseable
{
    public function parse($text)
    {
        return (new ParsedownExtra)->text($text);
    }
}
