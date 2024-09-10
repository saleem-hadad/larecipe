<?php

namespace SaleemHadad\LaRecipe\Interfaces;

interface IDocumentationService
{
    /**
     * @return mixed
     */
    public function get(GetDocumentRequest $getDocumentRequest);
}
