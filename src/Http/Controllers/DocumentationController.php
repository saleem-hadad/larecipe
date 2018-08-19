<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

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
    }

    /**
     * Redirect the index page of docs to the default version.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index()
    {
        return redirect()->route('docs.show', config('larecipe.versions.default'));
    }

    /**
     * @param $version
     * @param null $page
     * @return null|string|string[]
     * @throws \Exception
     */
    public function show($version, $page = null)
    {
        return $this->documentation->parse('hello');
    }
}
