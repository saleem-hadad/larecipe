<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
use BinaryTorch\LaRecipe\Contracts\ISidebarProvider;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;
use BinaryTorch\LaRecipe\Extensions\SidebarSectionActivator;
use BinaryTorch\LaRecipe\Models\Sidebar;
use Illuminate\Filesystem\Filesystem;

class FileSidebarProvider implements ISidebarProvider
{
    /**
     * @var Filesystem
     */
    protected $filesystem;
    protected $markdownParser;

    /**
     * DocumentFinder constructor.
     */
    public function __construct(Filesystem $filesystem, MarkdownParser $markdownParser)
    {
        $this->filesystem = $filesystem;
        $this->markdownParser = $markdownParser;
    }

    public function get(GetDocumentRequest $getDocumentRequest)
    {
        $larecipePath = config('larecipe.source');
        $basePath = base_path(trim(implode('/', [
            $larecipePath, 
            $getDocumentRequest->getLanguage(),
            $getDocumentRequest->getVersion(),
            'index.md'
        ]), '/'));

        if (! $this->filesystem->exists($basePath)) { 
            return null;
        }

        $this->markdownParser->addExtension(new SidebarSectionActivator($getDocumentRequest));

        $sidebarMarkdownContent = $this->filesystem->get($basePath);
        $sidebarParsedContent = $this->markdownParser->parse($sidebarMarkdownContent);

        return Sidebar::create(['content' => $sidebarParsedContent]);
    }
}
