<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Contracts\ISidebarProvider;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;

class FileSidebarProvider implements ISidebarProvider
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
        $larecipePath = config('larecipe.source');
        $basePath = base_path(trim(implode('/', [
            $larecipePath, 
            $getDocumentRequest->getLanguage(),
            $getDocumentRequest->getVersion(),
            'sidebar.md'
        ]), '/'));

        if (! $this->filesystem->exists($basePath)) { 
            return null;
        }

        $sidebarContent = $this->filesystem->get($basePath);
        return Sidebar::create(['content' => $sidebarContent]);
    }
}
