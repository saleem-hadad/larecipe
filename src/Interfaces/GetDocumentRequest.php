<?php

namespace SaleemHadad\LaRecipe\Interfaces;

interface GetDocumentRequest
{
    public function parse(String $path);
    public function getLanguage();
    public function getVersion();
    public function getPath();
    public function getLandingPath();
    public function getBasePath($url);
}
