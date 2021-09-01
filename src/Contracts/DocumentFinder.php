<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface DocumentFinder
{
    /**
     * @return mixed
     */
    public function find(GetDocumentRequest $getDocumentRequest);
}
