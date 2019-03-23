<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use BinaryTorch\LaRecipe\DocumentationRepository;

class SearchController extends Controller
{
    /**
     * @var DocumentationRepository
     */
    protected $documentationRepository;

    /**
     * DocumentationController constructor.
     */
    public function __construct(DocumentationRepository $documentationRepository)
    {
        $this->documentationRepository = $documentationRepository;

        if (config('larecipe.settings.auth')) {
            $this->middleware(['auth']);
        }
    }

    /**
     * Get the index of a given version.
     *
     * @param  string $version
     * @return Response
     */
    public function __invoke($version)
    {
        $this->authorizeAccessSearch($version);
        
        return response()->json(
            $this->documentationRepository->search($version)
        );
    }

    /**
     * @param  string $version
     * @return Response
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
