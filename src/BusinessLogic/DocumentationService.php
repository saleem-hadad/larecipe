<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use BinaryTorch\LaRecipe\Contracts\ISidebarProvider;
use BinaryTorch\LaRecipe\Contracts\IDocumentProvider;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
use BinaryTorch\LaRecipe\Contracts\IDocumentationService;
use BinaryTorch\LaRecipe\Http\Responses\DocumentationResponse;

class DocumentationService implements IDocumentationService
{
    protected $cache;
    protected $documentFinder;

    /**
     * DocumentationService constructor.
     */
    public function __construct(Cache $cache, ISidebarProvider $sidebarFinder, IDocumentProvider $documentFinder)
    {
        $this->cache = $cache;
        $this->sidebarFinder = $sidebarFinder;
        $this->documentFinder = $documentFinder;
    }

    public function get(GetDocumentRequest $getDocumentRequest)
    {
        return $this->cache->remember(function() use($getDocumentRequest) {
            return DocumentationResponse::create([
                'sidebar' => $this->sidebarFinder->get($getDocumentRequest),
                'document' => $this->documentFinder->get($getDocumentRequest)
            ]);
        }, $getDocumentRequest->getPath());
    }
}
