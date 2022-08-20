<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface IDocumentService
{
    /**
     * @return mixed
     */
    public function find(GetDocumentRequest $getDocumentRequest);
}
