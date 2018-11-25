<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

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
     */
    public function __construct(DocumentationRepository $documentationRepository)
    {
        $this->documentationRepository = $documentationRepository;
    }

    /**
     * Redirect the index page of docs to the default version.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index()
    {
        $redirectURL = config('larecipe.versions.default').'/'.config('larecipe.docs.landing');

        return redirect()->route('larecipe.show', $redirectURL);
    }

    /**
     * Show a documentation page.
     *
     * @param  string $version
     * @param  string|null $page
     * @return Response
     */
    public function show($version, $page = null)
    {
        $documentation = $this->documentationRepository->get($version, $page);

        if (Gate::has('viewLarecipe')) {
            $this->authorize('viewLarecipe', $documentation);
        }

        $published_versions_config = collect($this->documentationRepository->publishedVersions)->keyBy('version');
        if (
            isset($published_versions_config[$version])
            && isset($published_versions_config[$version]['auth'])
            && $published_versions_config[$version]['auth']
        ) {
            if (! auth()->guard($published_versions_config[$version]['guard'])->check()) {
                return redirect('login');
            }
        }

        if ($this->documentationRepository->isNotPublishedVersion($version)) {
            return redirect($documentation->defaultVersionUrl.'/'.$page, 301);
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
