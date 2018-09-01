<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Symfony\Component\DomCrawler\Crawler;
use BinaryTorch\LaRecipe\Models\Documentation;

class DocumentationController extends Controller
{
    /**
     * @var Documentation
     */
    private $documentation;

    /**
     * DocumentationController constructor.
     * @param Documentation $documentation
     */
    public function __construct(Documentation $documentation)
    {
        $this->documentation = $documentation;

        if (config('larecipe.settings.auth')) {
            $this->middleware(['web', 'auth']);
        }
    }

    /**
     * Redirect the index page of docs to the default version.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index()
    {
        return redirect()->route('docs.show', config('larecipe.versions.default') . '/' . config('larecipe.docs.landing'));
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
        $docsRoute         = config('larecipe.docs.route');
        $defaultVersion    = config('larecipe.versions.default');
        $publishedVersions = config('larecipe.versions.published');

        if (!$this->documentation->isPublishedVersion($version)) {
            return redirect($docsRoute . '/' . $defaultVersion . '/' . $version, 301);
        }

        $sectionPage = $page ?: config('larecipe.docs.landing');
        $content     = $this->documentation->get($version, $sectionPage);

        if (is_null($content)) {
            return response()->view('larecipe::docs', [
                'title'          => 'Page not found',
                'index'          => $this->documentation->getIndex($version),
                'content'        => view('larecipe::partials.404'),
                'currentVersion' => $version,
                'versions'       => $publishedVersions,
                'currentSection' => '',
                'canonical'      => null,
            ], 404);
        }

        $title   = (new Crawler($content))->filterXPath('//h1');
        $section = '';
        if ($this->documentation->sectionExists($version, $page)) {
            $section .= '/' . $page;
        } elseif (!is_null($page)) {
            return redirect($docsRoute . '/' . $version);
        }

        $canonical = null;
        if ($this->documentation->sectionExists($defaultVersion, $sectionPage)) {
            $canonical = $docsRoute . '/' . $defaultVersion . '/' . $sectionPage;
        }

        return view('larecipe::docs', [
            'title'          => count($title) ? $title->text() : null,
            'index'          => $this->documentation->getIndex($version),
            'content'        => $content,
            'currentVersion' => $version,
            'versions'       => $publishedVersions,
            'currentSection' => $section,
            'canonical'      => $canonical,
        ]);
    }
}
