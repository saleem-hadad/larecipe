<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Config;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest as GetDocumentRequestContract;

class GetDocumentRequest implements GetDocumentRequestContract
{
    protected $language;
    protected $version;
    protected $path;

    /**
     * @param String $path
     * @return $this
     */
    public function parse(String $path): GetDocumentRequest
    {
        $path = trim($path);

        $this->language = $this->parseLanguage($path);

        $this->version = $this->parseVersion($path);

        $this->path = $this->parsePath($path);

        return $this;
    }

    /**
     * @param $path
     * @return mixed|string|null
     */
    protected function parseLanguage($path)
    {
        if(! Config::isLanguageEnabled()) {
            return null;
        }
        
        $pathParts = explode('/', $path);

        return empty($pathParts[0]) ? Config::defaultLanguage() : $pathParts[0];
    }

    /**
     * @param $path
     * @return mixed|string|null
     */
    protected function parseVersion($path)
    {
        if(! Config::isVersionEnabled()) {
            return null;
        }

        $pathParts = explode('/', $path);

        $versionIndex = Config::isLanguageEnabled() ? 1 : 0;

        return empty($pathParts[$versionIndex]) ? Config::defaultVersion() : $pathParts[$versionIndex];
    }

    protected function parsePath($path)
    {
        return trim(str_replace([$this->language, $this->version], "", $path), '/');
    }
    
    public function getLanguage()
    {
        return $this->language;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getPath()
    {
        return $this->path;
    }
}
