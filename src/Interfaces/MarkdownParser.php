<?php

namespace SaleemHadad\LaRecipe\Interfaces;

interface MarkdownParser
{
    public function addExtension($extension);
    
    /**
     * Parse the given source to Markdown, using your Markdown parser of choice.
     *
     * @param $source
     * @return null|string|string[]
     */
    public function parse($source);
}
