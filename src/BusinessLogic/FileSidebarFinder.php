<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Contracts\ISidebarFinder;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;

class FileSidebarFinder implements ISidebarFinder
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
        $basePath = base_path(trim(implode('/', [
            $larecipePath, 
            $getDocumentRequest->getLanguage(),
            $getDocumentRequest->getVersion(),
            'sidebar.md'
        ]), '/'));

        if ($this->filesystem->exists($basePath)) { 
            return $this->filesystem->get($basePath);
        }
        
        return null;
    }
}
