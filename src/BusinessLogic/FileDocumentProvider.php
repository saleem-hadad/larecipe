<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Contracts\IDocumentProvider;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;

class FileDocumentProvider implements IDocumentProvider
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

    public function get(GetDocumentRequest $getDocumentRequest)
    {
        $larecipeSourcePath = config('larecipe.source');
        $filePath = $getDocumentRequest->getPath();
        $relativePath = trim(implode('/', [$larecipeSourcePath,  "$filePath.md"]), '/');
        $basePath = base_path($relativePath);

        if ($this->filesystem->exists($basePath)) { 
            return $this->filesystem->get($basePath);
        }
        
        return null;
    }
}
