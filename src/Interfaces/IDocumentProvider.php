<?php

namespace SaleemHadad\LaRecipe\Interfaces;

interface IDocumentProvider
{
    /**
     * @return mixed
     */
    public function get(GetDocumentRequest $getDocumentRequest);
}
