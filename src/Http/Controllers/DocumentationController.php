<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use BinaryTorch\LaRecipe\DocumentationRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

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

        if (config('larecipe.settings.auth')) {
            $this->middleware(['auth']);
        }
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

    /**
     * Show a documentation page. within a
     * folder eg. /docs/1.0/api/overview
     *
     * @param string $version
     * @param string $folder
     * @param null $page
     * @return Response
     */
    public function showFolder($version, $folder, $page = null)
    {
        return $this->show($version, $folder . DIRECTORY_SEPARATOR . $page);
    }
}
