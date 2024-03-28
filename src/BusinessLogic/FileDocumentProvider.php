<?php

namespace SaleemHadad\LaRecipe\BusinessLogic;

use SaleemHadad\LaRecipe\Models\Model;
use Illuminate\Filesystem\Filesystem;
use SaleemHadad\LaRecipe\Models\Document;
use SaleemHadad\LaRecipe\Interfaces\IDocumentProvider;
use SaleemHadad\LaRecipe\Interfaces\GetDocumentRequest;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class FileDocumentProvider implements IDocumentProvider
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param GetDocumentRequest $getDocumentRequest
     * @return Model|null
     * @throws FileNotFoundException
     */
    public function get(GetDocumentRequest $getDocumentRequest): ?Document
    {
        $larecipeSourcePath = config('larecipe.source');
        $filePath = $getDocumentRequest->getPath();
        $relativePath = trim(implode('/', [$larecipeSourcePath,  "$filePath.md"]), '/');
        $basePath = base_path($relativePath);

        if ($this->filesystem->missing($basePath)) {
            return null;
        }

        $documentContent = $this->filesystem->get($basePath);
        return Document::create(['content' => $documentContent]);
    }
}
