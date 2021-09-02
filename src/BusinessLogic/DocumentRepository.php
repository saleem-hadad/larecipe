<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Models\Model;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Models\Document;
use BinaryTorch\LaRecipe\Contracts\DocumentFinder;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
use BinaryTorch\LaRecipe\Http\Responses\DocumentationResponse;
use BinaryTorch\LaRecipe\Contracts\DocumentRepository as DocumentRepositoryContract;

class DocumentRepository implements DocumentRepositoryContract
{
    protected $cache;
    protected $documentFinder;

    /**
     * DocumentFinder constructor.
     */
    public function __construct(Cache $cache, DocumentFinder $documentFinder)
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
