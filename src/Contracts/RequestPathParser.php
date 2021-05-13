<?php


namespace BinaryTorch\LaRecipe\Contracts;

interface RequestPathParser
{
    public function parse(String $path);

    public function getDocumentBasePath(): string;
}
