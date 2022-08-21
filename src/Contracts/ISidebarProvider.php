<?php

namespace BinaryTorch\LaRecipe\Contracts;

interface ISidebarProvider
{
    /**
     * @return mixed
     */
    public function get(GetDocumentRequest $getDocumentRequest);
}
