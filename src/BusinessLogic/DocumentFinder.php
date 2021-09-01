<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
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

    public function find(GetDocumentRequest $getDocumentRequest)
    {
        $larecipePath = config('larecipe.path');
        $filePath = $getDocumentRequest->getPath();
        $basePath = base_path(trim(implode('/', [$larecipePath, $filePath . '.md']), '/'));

        if ($this->filesystem->exists($basePath)) { 
            return $this->filesystem->get($basePath);
        }
        
        return null;
    }
}
