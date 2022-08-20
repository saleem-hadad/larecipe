<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Models\Document;
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
    public function __construct(Cache $cache, IDocumentFinder $documentFinder)
    {
        $this->cache = $cache;
        $this->documentFinder = $documentFinder;
    }

    public function find(GetDocumentRequest $getDocumentRequest)
    {
        return $this->cache->remember(function() use($getDocumentRequest) {
            return DocumentationResponse::create([
                'sidebar' => Sidebar::create(['content' => '<div></div>']),
                'document' => Document::create(['content' => $this->documentFinder->find($getDocumentRequest)])
            ]);
        }, $getDocumentRequest->getPath());
    }
}
