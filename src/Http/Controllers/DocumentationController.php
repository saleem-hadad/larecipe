<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use BinaryTorch\LaRecipe\DocumentationRepository;
use BinaryTorch\LaRecipe\Traits\HasProducts;

class DocumentationController extends Controller
{
    use HasProducts;
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

        return response()->view('larecipe::docs',  [
            'title'          => $documentation->title,
            'index'          => $documentation->index,
            'product'        => null,
            'content'        => $documentation->content,
            'currentVersion' => $version,
            'versions'       => $this->getVersions($documentation->publishedVersions),
            'currentSection' => $documentation->currentSection,
            'canonical'      => $documentation->canonical,
            'products'       => $this->getProducts($documentation->publishedVersions),
        ] , $documentation->statusCode);
    }
    /**
     * Show a product documentation page.
     *
     * @param $product
     * @param $version
     * @param null $page
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function showProduct($product, $version, $page = null)
    {

        $documentation = $this->documentationRepository->getProduct($product, $version, $page);

        if (Gate::has('viewLarecipe')) {
            $this->authorize('viewLarecipe', $documentation);
        }

        if(!$this->documentationRepository->productExists($product))
            return redirect()->route(
                'larecipe.show',
                [
                    'version' => config('larecipe.versions.default'),
                    'page' => config('larecipe.docs.landing')
                ]
            );

        if ($this->documentationRepository->isNotPublishedProductVersion($product,$version))
            return redirect()->route(
                'larecipe.show.product',
                [
                    'product' => $product,
                    'version' => config('larecipe.versions.default'),
                    'page' => config('larecipe.docs.landing')
                ]
            );


        return response()->view('larecipe::docs',  [
            'title'          => $documentation->title,
            'index'          => $documentation->index,
            'product'        => $product,
            'content'        => $documentation->content,
            'currentVersion' => $version,
            'versions'       => $this->getProductVersions($documentation->publishedVersions, $product),
            'currentSection' => $documentation->currentSection,
            'canonical'      => $documentation->canonical,
            'products'       => $this->getProducts($documentation->publishedVersions),
        ] , $documentation->statusCode);
    }
}
