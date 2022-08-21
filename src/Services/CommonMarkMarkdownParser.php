<?php

namespace BinaryTorch\LaRecipe\Services;

use League\CommonMark\CommonMarkConverter;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;


use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

class ExternalLinkProcessor
{
    private $environment;

    public function __construct(EnvironmentInterface $environment)
    {
        $this->environment = $environment;
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $document = $event->getDocument();
        $walker = $document->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();

            if (!($node instanceof Link) || !$event->isEntering()) {
                continue;
            }

            $url = $node->getUrl();
            if ($url == "overview") {
                $node->data->append('attributes/class', 'active');
            }
        }
    }
}

class CommonMarkMarkdownParser implements MarkdownParser
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


        $listener = new ExternalLinkProcessor($converter->getEnvironment());
        $converter->getEnvironment()->addEventListener(DocumentParsedEvent::class, [$listener, 'onDocumentParsed']);

        return $converter->convert($source);
    }
}
