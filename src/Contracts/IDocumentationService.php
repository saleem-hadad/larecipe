<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface IDocumentationService
{
    /**
     * @return mixed
     */
    public function get(GetDocumentRequest $getDocumentRequest);
}
