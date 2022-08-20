<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Models\Document;
use BinaryTorch\LaRecipe\Contracts\ISidebarFinder;
use BinaryTorch\LaRecipe\Contracts\IDocumentFinder;
use BinaryTorch\LaRecipe\Contracts\IDocumentService;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
use BinaryTorch\LaRecipe\Http\Responses\DocumentationResponse;

class DocumentService implements IDocumentService
{
    protected $cache;
    protected $documentFinder;

    /**
     * DocumentService constructor.
     */
    public function __construct(Cache $cache, ISidebarFinder $sidebarFinder, IDocumentFinder $documentFinder)
    {
        $this->cache = $cache;
        $this->sidebarFinder = $sidebarFinder;
        $this->documentFinder = $documentFinder;
    }

    public function find(GetDocumentRequest $getDocumentRequest)
    {
        return $this->cache->remember(function() use($getDocumentRequest) {
            $sidebarContent = $this->sidebarFinder->find($getDocumentRequest);
            $documentContent = $this->documentFinder->find($getDocumentRequest);

            return DocumentationResponse::create([
                'sidebar' => Sidebar::create(['content' => $sidebarContent]),
                'document' => Document::create(['content' => $documentContent])
            ]);
        }, $getDocumentRequest->getPath());
    }
}
