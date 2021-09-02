<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

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

        $this->path = $path;

        return $this;
    }

    /**
     * @param $path
     * @return mixed|string|null
     */
    protected function parseLanguage($path)
    {
        if(! config('larecipe.languages.enabled')) {
            return null;
        }
        
        $pathParts = explode('/', $path);

        return empty($pathParts[0]) ? config('larecipe.languages.default') : $pathParts[0];
    }

    /**
     * @param $path
     * @return mixed|string|null
     */
    protected function parseVersion($path)
    {
        if(! config('larecipe.versions.enabled')) {
            return null;
        }

        $pathParts = explode('/', $path);

        $versionIndex = config('larecipe.languages.enabled') ? 1 : 0;

        return empty($pathParts[$versionIndex]) ? config('larecipe.versions.default') : $pathParts[$versionIndex];
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

    public function getLandingPath()
    {
        $language = '';
        $version = '';

        if(config('larecipe.languages.enabled') && config('larecipe.languages.default') && in_array(config('larecipe.languages.default'), config('larecipe.languages.published'))) {
            $language = config('larecipe.languages.default');
        }

        if(config('larecipe.versions.enabled') && config('larecipe.versions.default') && in_array(config('larecipe.versions.default'), config('larecipe.versions.published'))) {
            $version = config('larecipe.versions.default');
        }

        return trim(implode('/', [
            $language,
            $version,
            config('larecipe.landing')
        ]), '/');
    }
}
