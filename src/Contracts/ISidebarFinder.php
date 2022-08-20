<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface ISidebarFinder
{
    /**
     * @return mixed
     */
    public function find(GetDocumentRequest $getDocumentRequest);
}
