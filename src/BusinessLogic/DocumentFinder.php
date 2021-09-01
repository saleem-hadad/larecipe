<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Models\Model;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Models\Document;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
use BinaryTorch\LaRecipe\Http\Responses\DocumentationResponse;
use BinaryTorch\LaRecipe\Contracts\DocumentFinder as DocumentFinderContract;

class DocumentFinder implements DocumentFinderContract
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * DocumentFinder constructor.
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param $path
     * @return DocumentationResponse
     */
    public function find(GetDocumentRequest $getDocumentRequest)
    {
        return $this->filesystem->get($getDocumentRequest->getPath());
    }
}
