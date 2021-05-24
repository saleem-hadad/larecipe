<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use BinaryTorch\LaRecipe\DocumentationRepository;

class DocumentationController extends Controller
{
    /**
     * @var DocumentationRepository
     */
    protected $documentationRepository;

    /**
     * DocumentationController constructor.
     * @param DocumentationRepository $documentationRepository
     */
    public function __construct(DocumentationRepository $documentationRepository)
    {
        $this->documentationRepository = $documentationRepository;

        if (config('larecipe.settings.guard')) {
            Auth::shouldUse(config('larecipe.settings.guard'));
        }

        if (config('larecipe.settings.auth')) {
            $this->middleware(['auth']);
        }else{
            if(config('larecipe.settings.middleware')){
                $this->middleware(config('larecipe.settings.middleware'));
            }
        }
    }

    /**
     * Redirect the index page of docs to the default version.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return redirect()->route(
            'larecipe.show',
            [
                'version' => config('larecipe.versions.default'),
                'page' => config('larecipe.docs.landing')
            ]
        );
    }

    /**
     * Show a documentation page.
     *
     * @param $version
     * @param null $page
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($version, $page = null)
    {
        $documentation = $this->documentationRepository->get($version, $page);
        
        if (Gate::has('viewLarecipe')) {
            $this->authorize('viewLarecipe', $documentation);
        }

        if ($this->documentationRepository->isNotPublishedVersion($version)) {
            return redirect()->route(
                'larecipe.show',
                [
                    'version' => config('larecipe.versions.default'),
                    'page' => config('larecipe.docs.landing')
                ]
            );
        }

        return response()->view('larecipe::docs', [
            'title'          => $documentation->title,
            'index'          => $documentation->index,
            'content'        => $documentation->content,
            'currentVersion' => $version,
            'versions'       => $documentation->publishedVersions,
            'currentSection' => $documentation->currentSection,
            'canonical'      => $documentation->canonical,
        ], $documentation->statusCode);
    }
}
