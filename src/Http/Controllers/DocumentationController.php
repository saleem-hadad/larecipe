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
     * Redirect the index page of docs to the current version.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index()
    {
        return redirect()->route('docs.show', config('larecipe.versions.default'));
    }
    
    public function show($version, $page = null)
    {
        return $this->documentation->parse('hello');
    }
}
