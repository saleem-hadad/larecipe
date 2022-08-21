<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Models\Document;
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
            $sidebarContent = $this->sidebarFinder->get($getDocumentRequest);
            $documentContent = $this->documentFinder->get($getDocumentRequest);

            return DocumentationResponse::create([
                'sidebar' => Sidebar::create(['content' => $sidebarContent]),
                'document' => Document::create(['content' => $documentContent])
            ]);
        }, $getDocumentRequest->getPath());
    }
}
