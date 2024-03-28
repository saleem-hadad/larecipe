<?php

namespace SaleemHadad\LaRecipe\Extensions;

use League\CommonMark\Event\DocumentParsedEvent;
use SaleemHadad\LaRecipe\Interfaces\GetDocumentRequest;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

class SidebarLinksModifierExtension
{
    private $getDocumentRequest;

    public function __construct(GetDocumentRequest $getDocumentRequest)
    {
        $this->getDocumentRequest = $getDocumentRequest;
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $document = $event->getDocument();
        $walker = $document->walker();

        // TODO: change to query based search
        while ($event = $walker->next()) {
            $node = $event->getNode();

            if (!($node instanceof Link) || !$event->isEntering()) {
                continue;
            }

            $url = $node->getUrl();

            $node->setUrl(url($this->getDocumentRequest->getBasePath($url)));
        }
    }
}