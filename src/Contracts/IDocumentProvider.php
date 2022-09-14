<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface IDocumentProvider
{
    /**
     * @return mixed
     */
    public function get(GetDocumentRequest $getDocumentRequest);
}
