<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Cache;
use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Models\Model;
use BinaryTorch\LaRecipe\Models\Sidebar;
use BinaryTorch\LaRecipe\Models\Document;
use BinaryTorch\LaRecipe\Contracts\RequestPathParser;
use BinaryTorch\LaRecipe\Http\Responses\DocumentationResponse;
use BinaryTorch\LaRecipe\Contracts\DocumentFinder as DocumentFinderContract;

class DocumentFinder implements DocumentFinderContract
{
    /**
     * @var RequestPathParser
     */
    protected $requestPathParser;

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * DocumentFinder constructor.
     */
    public function __construct(RequestPathParser $requestPathParser, Filesystem $files, Cache $cache)
    {
        $this->requestPathParser = $requestPathParser;
        $this->files = $files;
        $this->cache = $cache;
    }

    /**
     * @param $path
     * @return DocumentationResponse
     */
    public function find($path): DocumentationResponse
    {
        $documentBasePath = $this->requestPathParser->parse($path)->getDocumentBasePath();

        $content = $this->cache->remember(function() use($documentBasePath) {
            try {
                return $this->files->get($documentBasePath);
            }catch (\Exception $exception) {
                return null;
            }
        }, $path);

        return DocumentationResponse::create([
            'sidebar' => Sidebar::create(['content' => '<div></div>']),
            'document' => Document::create(['content' => $content])
        ]);
    }
}
