<?php

namespace SaleemHadad\LaRecipe\BusinessLogic;

use SaleemHadad\LaRecipe\Cache;
use SaleemHadad\LaRecipe\Interfaces\ISidebarProvider;
use SaleemHadad\LaRecipe\Interfaces\IDocumentProvider;
use SaleemHadad\LaRecipe\Interfaces\GetDocumentRequest;
use SaleemHadad\LaRecipe\Interfaces\IDocumentationService;
use SaleemHadad\LaRecipe\Http\Responses\DocumentationResponse;

class DocumentationService implements IDocumentationService
{
    protected $cache;
    protected $sidebarFinder;
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
