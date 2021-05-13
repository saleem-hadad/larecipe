<?php


namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Contracts\RequestPathParser as RequestPathParserContract;

class RequestPathParser implements RequestPathParserContract
{
    protected $language;
    protected $version;
    protected $relativePath;

    /**
     * @param String $path
     * @return $this
     */
    public function parse(String $path): RequestPathParser
    {
        $path = trim($path);

//        $this->language = $this->parseLanguage($path);
        $this->version = $this->parseVersion($path);
        $this->relativePath = $this->parseRelativePath($path);

        return $this;
    }

    /**
     * @return string
     */
    public function getDocumentBasePath(): string
    {
        $documentPath = trim(config('larecipe.docs.path'), '/');

        if($this->version) {
            $documentPath .= "/$this->version";
        }

        $documentPath .= "/$this->relativePath.md";

        return base_path($documentPath);
    }

    private function parseVersion($path)
    {
        if(config('larecipe.versions.enabled')) {
            $pathParts = explode('/', $path);

            return $pathParts[0] ?: config('larecipe.versions.default');
        }

        return null;
    }

    private function parseRelativePath($path)
    {
        $relativePath = $path;

        if(config('larecipe.versions.enabled')) {
            $pathParts = explode('/', $path);

            unset($pathParts[0]);

            $relativePath = implode('/', $pathParts);
        }

        if($relativePath == "") {
            return config('larecipe.docs.landing');
        }

        return $relativePath;
    }
}
