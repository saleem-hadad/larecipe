<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface DocumentRepository
{
    /**
     * @return mixed
     */
    public function find(GetDocumentRequest $getDocumentRequest);
}
