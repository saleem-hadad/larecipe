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
     * SearchController constructor.
     * @param DocumentationRepository $documentationRepository
     */
    public function __construct(DocumentationRepository $documentationRepository)
    {
        $this->documentationRepository = $documentationRepository;

        if (config('larecipe.settings.auth')) {
            $this->middleware(['auth']);
        }else{
            if(config('larecipe.settings.middleware')){
                $this->middleware(config('larecipe.settings.middleware'));
            }
        }

    }

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
