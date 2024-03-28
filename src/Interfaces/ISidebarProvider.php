<?php

namespace SaleemHadad\LaRecipe\Interfaces;

interface ISidebarProvider
{
    /**
     * @return mixed
     */
    public function get(GetDocumentRequest $getDocumentRequest);
}
