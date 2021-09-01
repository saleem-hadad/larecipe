<?php


namespace BinaryTorch\LaRecipe\Contracts;

interface GetDocumentRequest
{
    public function parse(String $path);
    public function getLanguage();
    public function getVersion();
    public function getPath();
}
