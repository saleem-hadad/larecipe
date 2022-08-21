<?php

namespace BinaryTorch\LaRecipe\Services;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Event\DocumentParsedEvent;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

class CommonMarkMarkdownParser implements MarkdownParser
{
    private $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => true,
        ]);
    }

    public function addExtension($extension)
    {
        $this->converter
            ->getEnvironment()
            ->addEventListener(DocumentParsedEvent::class, [$extension, 'onDocumentParsed']);
    }

    /**
     * @param $source
     * @return string|string[]|null
     */
    public function parse($source)
    {
        if(empty($source)) { return null; }

        return $this->converter->convert($source);
    }
}
