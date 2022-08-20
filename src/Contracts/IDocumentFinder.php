<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface IDocumentFinder
{
    /**
     * @return mixed
     */
    public function find(GetDocumentRequest $getDocumentRequest);
}
