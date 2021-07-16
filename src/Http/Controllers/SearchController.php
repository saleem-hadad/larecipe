<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use BinaryTorch\LaRecipe\DocumentationRepository;

class SearchController extends Controller
{
    /**
     * Get the index of a given version.
     *
     * @param $version
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($version)
    {
        $this->authorizeAccessSearch($version);
        
        return response()->json(
            $this->documentationRepository->search($version)
        );
    }

    /**
     * @param $version
     */
    protected function authorizeAccessSearch($version)
    {
        abort_if(
            $this->documentationRepository->isNotPublishedVersion($version)
            ||
            config('larecipe.search.default') != 'internal'
            || 
            ! config('larecipe.search.enabled')
        , 403);
    }
}
