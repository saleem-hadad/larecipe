<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Contracts\IDocumentFinder;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;

class FileDocumentFinder implements IDocumentFinder
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
        $larecipePath = config('larecipe.source');
        $filePath = $getDocumentRequest->getPath();
        $basePath = base_path(trim(implode('/', [$larecipePath, $filePath . '.md']), '/'));

        if ($this->filesystem->exists($basePath)) { 
            return $this->filesystem->get($basePath);
        }
        
        return null;
    }
}
