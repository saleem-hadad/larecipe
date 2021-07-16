<?php

namespace BinaryTorch\LaRecipe\BusinessLogic;

use BinaryTorch\LaRecipe\Contracts\RequestPathParser as RequestPathParserContract;

class RequestPathParser implements RequestPathParserContract
{
    protected $parts = [];

    /**
     * @param String $path
     * @return $this
     */
    public function parse(String $path): RequestPathParser
    {
        $path = trim($path);

        $this->parts['language'] = $this->parseLanguage($path);

        $this->parts['version'] = $this->parseVersion($path);

        $this->parts['relativePath'] = $this->parseRelativePathWithMarkdownExtension($path);

        return $this;
    }

    public function getDefaultLandingPath(): string
    {
        $parts = [
            $this->parseLanguage(null),
            $this->parseVersion(null),
            config('larecipe.settings.landing')
        ];


        return implode('/', $parts);
    }

    /**
     * @return string
     */
    public function getDocumentBasePath(): string
    {
        $documentPath = trim(config('larecipe.settings.path'), '/');

        foreach ($this->parts as $key => $value) {
            if($value) {
                $documentPath .= "/$value";
            }
        }

        return base_path($documentPath);
    }

    /**
     * @return array
     */
    public function getPathParts(): array
    {
        return $this->parts;
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

    /**
     * @param $path
     * @return mixed|string|null
     */
    protected function parseRelativePathWithMarkdownExtension($path)
    {
        $relativePath = $this->removeLanguageIfEnabled($path);

        $relativePath = $this->removeVersionIfEnabled($relativePath);

        if ($relativePath == "") {
             $relativePath = config('larecipe.settings.landing');
        }

        return "$relativePath.md";
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function removeLanguageIfEnabled($path)
    {
        if(! config('larecipe.languages.enabled')) { return $path; }

        $pathParts = explode('/', $path);

        unset($pathParts[0]);

        return implode('/', $pathParts);
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function removeVersionIfEnabled($path)
    {
        if(! config('larecipe.versions.enabled')) { return $path; }

        $pathParts = explode('/', $path);

        unset($pathParts[0]);

        return implode('/', $pathParts);
    }
}
