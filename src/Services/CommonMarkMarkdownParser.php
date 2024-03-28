<?php

namespace SaleemHadad\LaRecipe\Services;

use SaleemHadad\LaRecipe\Extensions\TableOfContentsSidebarExtension;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Event\DocumentParsedEvent;
use SaleemHadad\LaRecipe\Interfaces\MarkdownParser;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;

class CommonMarkMarkdownParser implements MarkdownParser
{
    private $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => true,
            'heading_permalink' => [
                'insert' => 'before',
                'fragment_prefix' => '',
                'id_prefix' => '',
                'symbol' => '#',
                'max_heading_level' => 2,
                'min_heading_level' => 2,
            ],
            'table_of_contents' => [
                'position' => 'placeholder',
                'min_heading_level' => 1,
                'max_heading_level' => 3,
                'placeholder' => '[TOC]',
            ],
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

        $this->converter->getEnvironment()->addExtension(new TaskListExtension);
        $this->converter->getEnvironment()->addExtension(new TableExtension);
        $this->converter
            ->getEnvironment()->addExtension(new HeadingPermalinkExtension);
        $this->converter
            ->getEnvironment()->addExtension(new TableOfContentsExtension);
        $this->converter->getEnvironment()->addExtension(new TableOfContentsSidebarExtension);
        $this->converter
            ->getEnvironment()->addExtension(new AttributesExtension());


        return $this->converter->convert($source);
    }
}
